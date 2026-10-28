<?php

namespace Laravel\Horizon\Contracts;

use stdClass;

interface WorkerStateRepository
{
    /**
     * Get the last recorded state of every worker of the running supervisors.
     *
     * @return array<int, \stdClass>
     */
    public function all(): array;

    /**
     * Get the last recorded state of every worker of the given supervisor.
     *
     * @return array<int, \stdClass> keyed by process ID
     */
    public function forSupervisor(string $supervisor): array;

    /**
     * Get the last recorded state of the given worker.
     */
    public function find(string $supervisor, int $pid): ?stdClass;

    /**
     * Replace the state of the given worker.
     *
     * @param  array<string, mixed>  $state
     */
    public function put(string $supervisor, int $pid, array $state): void;

    /**
     * Remove the state of the given worker.
     */
    public function forget(string $supervisor, int $pid): void;

    /**
     * Keep the states of the given supervisor's workers for the given number of seconds.
     */
    public function expire(string $supervisor, int $seconds): void;
}
