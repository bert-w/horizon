<?php

namespace Laravel\Horizon\Repositories;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkerStateRepository;
use stdClass;

class RedisWorkerStateRepository implements WorkerStateRepository
{
    use UsesClusterAwarePipeline;

    /**
     * Create a new repository instance.
     */
    public function __construct(public RedisFactory $redis)
    {
    }

    /**
     * Get the last recorded state of every worker of the running supervisors.
     *
     * @return array<int, \stdClass>
     */
    public function all(): array
    {
        $names = app(SupervisorRepository::class)->names();

        $records = $this->pipeline(function ($pipe) use ($names) {
            foreach ($names as $name) {
                $pipe->hgetall($this->key($name));
            }
        });

        return collect($records)
            ->collapse()
            ->map(fn (string $state) => json_decode($state))
            ->values()
            ->all();
    }

    /**
     * Get the last recorded state of every worker of the given supervisor.
     *
     * @return array<int, \stdClass> keyed by process ID
     */
    public function forSupervisor(string $supervisor): array
    {
        return collect($this->connection()->hgetall($this->key($supervisor)))
            ->map(fn (string $state) => json_decode($state))
            ->all();
    }

    /**
     * Get the last recorded state of the given worker.
     */
    public function find(string $supervisor, int $pid): ?stdClass
    {
        $state = $this->connection()->hget($this->key($supervisor), $pid);

        return $state ? json_decode($state) : null;
    }

    /**
     * Replace the state of the given worker.
     *
     * @param  array<string, mixed>  $state
     */
    public function put(string $supervisor, int $pid, array $state): void
    {
        $this->connection()->hset($this->key($supervisor), $pid, json_encode($state));
    }

    /**
     * Remove the state of the given worker.
     */
    public function forget(string $supervisor, int $pid): void
    {
        $this->connection()->hdel($this->key($supervisor), $pid);
    }

    /**
     * Keep the states of the given supervisor's workers for the given number of seconds.
     */
    public function expire(string $supervisor, int $seconds): void
    {
        $this->connection()->expire($this->key($supervisor), $seconds);
    }

    /**
     * Get the key of the hash holding the states of the given supervisor's workers.
     */
    protected function key(string $supervisor): string
    {
        return 'supervisor:'.$supervisor.':workers';
    }

    /**
     * Get the Redis connection instance.
     */
    protected function connection(): Connection
    {
        return $this->redis->connection('horizon');
    }
}
