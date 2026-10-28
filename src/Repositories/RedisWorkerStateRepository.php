<?php

namespace Laravel\Horizon\Repositories;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkerStateRepository;
use Laravel\Horizon\RunningJob;
use Laravel\Horizon\WorkerState;
use Throwable;

class RedisWorkerStateRepository implements WorkerStateRepository
{
    use UsesClusterAwarePipeline;

    /**
     * The seconds the states outlive the last write or supervisor loop, as the supervisor record does.
     */
    public const TTL = 30;

    /**
     * Create a new repository instance.
     */
    public function __construct(public RedisFactory $redis)
    {
    }

    /**
     * Get the last recorded state of every worker of the running supervisors.
     *
     * @return array<int, \Laravel\Horizon\WorkerState>
     */
    public function all(): array
    {
        $names = app(SupervisorRepository::class)->names();

        $records = $this->pipeline(function ($pipe) use ($names) {
            foreach ($names as $name) {
                $pipe->hgetall($this->key($name));
            }
        });

        $states = [];

        foreach ($records as $record) {
            foreach ($record ?: [] as $state) {
                if ($decoded = $this->decode($state)) {
                    $states[] = $decoded;
                }
            }
        }

        return $states;
    }

    /**
     * Get the last recorded state of every worker of the given supervisor.
     *
     * @return array<int, \Laravel\Horizon\WorkerState> keyed by process ID
     */
    public function forSupervisor(string $supervisor): array
    {
        return array_filter(array_map(
            fn (string $state) => $this->decode($state),
            $this->connection()->hgetall($this->key($supervisor))
        ));
    }

    /**
     * Get the last recorded state of the given worker.
     */
    public function find(string $supervisor, int $pid): ?WorkerState
    {
        $state = $this->connection()->hget($this->key($supervisor), $pid);

        return $state ? $this->decode($state) : null;
    }

    /**
     * Replace the state of the worker it describes.
     *
     * The expiry is renewed as well, as a worker may outlive its supervisor when its states expired.
     */
    public function put(WorkerState $state): void
    {
        $this->pipeline(function ($pipe) use ($state) {
            $pipe->hset($this->key($state->supervisor), $state->pid, json_encode($state));
            $pipe->expire($this->key($state->supervisor), self::TTL);
        });
    }

    /**
     * Remove the state of the given worker.
     */
    public function forget(string $supervisor, int $pid): void
    {
        $this->connection()->hdel($this->key($supervisor), $pid);
    }

    /**
     * Keep the states of the given supervisor's workers while the supervisor is running.
     */
    public function refresh(string $supervisor): void
    {
        $this->connection()->expire($this->key($supervisor), self::TTL);
    }

    /**
     * Get the key of the hash holding the states of the given supervisor's workers.
     */
    protected function key(string $supervisor): string
    {
        return 'supervisor:'.$supervisor.':workers';
    }

    /**
     * Decode a stored worker state, or null when it can't be, e.g. one written by another Horizon version.
     */
    protected function decode(string $state): ?WorkerState
    {
        try {
            // Stored by jsonSerialize(), so the keys are the constructor's parameter names...
            $attributes = json_decode($state, true, flags: JSON_THROW_ON_ERROR);

            $attributes['job'] = isset($attributes['job']) ? new RunningJob(...$attributes['job']) : null;

            return new WorkerState(...$attributes);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the Redis connection instance.
     */
    protected function connection(): Connection
    {
        return $this->redis->connection('horizon');
    }
}
