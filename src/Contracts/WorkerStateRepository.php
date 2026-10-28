<?php

namespace Laravel\Horizon\Contracts;

interface WorkerStateRepository
{
    /**
     * Get the last recorded state of every worker of the running supervisors.
     *
     * @return array
     */
    public function all();

    /**
     * Get the last recorded state of every worker of the given supervisor, keyed by process ID.
     *
     * @param  string  $supervisor
     * @return array
     */
    public function forSupervisor($supervisor);

    /**
     * Get the last recorded state of the given worker.
     *
     * @param  string  $supervisor
     * @param  int  $pid
     * @return \stdClass|null
     */
    public function find($supervisor, $pid);

    /**
     * Replace the state of the given worker.
     *
     * @param  string  $supervisor
     * @param  int  $pid
     * @param  array  $state
     * @return void
     */
    public function put($supervisor, $pid, array $state);

    /**
     * Remove the state of the given worker.
     *
     * @param  string  $supervisor
     * @param  int  $pid
     * @return void
     */
    public function forget($supervisor, $pid);

    /**
     * Keep the states of the given supervisor's workers for the given number of seconds.
     *
     * @param  string  $supervisor
     * @param  int  $seconds
     * @return void
     */
    public function expire($supervisor, $seconds);
}
