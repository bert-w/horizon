<?php

namespace Laravel\Horizon\Repositories;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkerStateRepository;

class RedisWorkerStateRepository implements WorkerStateRepository
{
    use UsesClusterAwarePipeline;

    /**
     * The Redis connection instance.
     *
     * @var \Illuminate\Contracts\Redis\Factory
     */
    public $redis;

    /**
     * Create a new repository instance.
     *
     * @param  \Illuminate\Contracts\Redis\Factory  $redis
     * @return void
     */
    public function __construct(RedisFactory $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Get the last recorded state of every worker of the running supervisors.
     *
     * @return array
     */
    public function all()
    {
        $names = app(SupervisorRepository::class)->names();

        $records = $this->pipeline(function ($pipe) use ($names) {
            foreach ($names as $name) {
                $pipe->hgetall($this->key($name));
            }
        });

        return collect($records)
            ->flatMap(fn ($states) => (array) $states)
            ->map(fn ($state) => json_decode($state))
            ->values()
            ->all();
    }

    /**
     * Get the last recorded state of every worker of the given supervisor, keyed by process ID.
     *
     * @param  string  $supervisor
     * @return array
     */
    public function forSupervisor($supervisor)
    {
        return collect($this->connection()->hgetall($this->key($supervisor)))
            ->map(fn ($state) => json_decode($state))
            ->all();
    }

    /**
     * Get the last recorded state of the given worker.
     *
     * @param  string  $supervisor
     * @param  int  $pid
     * @return \stdClass|null
     */
    public function find($supervisor, $pid)
    {
        $state = $this->connection()->hget($this->key($supervisor), $pid);

        return $state ? json_decode($state) : null;
    }

    /**
     * Replace the state of the given worker.
     *
     * @param  string  $supervisor
     * @param  int  $pid
     * @param  array  $state
     * @return void
     */
    public function put($supervisor, $pid, array $state)
    {
        $this->connection()->hset($this->key($supervisor), $pid, json_encode(
            $state, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        ));
    }

    /**
     * Remove the state of the given worker.
     *
     * @param  string  $supervisor
     * @param  int  $pid
     * @return void
     */
    public function forget($supervisor, $pid)
    {
        $this->connection()->hdel($this->key($supervisor), $pid);
    }

    /**
     * Keep the states of the given supervisor's workers for the given number of seconds.
     *
     * @param  string  $supervisor
     * @param  int  $seconds
     * @return void
     */
    public function expire($supervisor, $seconds)
    {
        $this->connection()->expire($this->key($supervisor), $seconds);
    }

    /**
     * Get the key holding the states of the given supervisor's workers.
     *
     * @param  string  $supervisor
     * @return string
     */
    protected function key($supervisor)
    {
        return 'supervisor:'.$supervisor.':workers';
    }

    /**
     * Get the Redis connection instance.
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    protected function connection()
    {
        return $this->redis->connection('horizon');
    }
}
