<?php

namespace Laravel\Horizon\Contracts;

use Laravel\Horizon\WorkerState;

interface WorkerStateRepository
{
    /**
     * Get the last recorded state of every worker of the running supervisors.
     *
     * @return array<int, \Laravel\Horizon\WorkerState>
     */
    public function all(): array;

    /**
     * Get the last recorded state of every worker of the given supervisor.
     *
     * @return array<int, \Laravel\Horizon\WorkerState> keyed by process ID
     */
    public function forSupervisor(string $supervisor): array;

    /**
     * Get the last recorded state of the given worker.
     */
    public function find(string $supervisor, int $pid): ?WorkerState;

    /**
     * Replace the state of the worker it describes.
     */
    public function put(WorkerState $state): void;

    /**
     * Remove the state of the given worker.
     */
    public function forget(string $supervisor, int $pid): void;

    /**
     * Keep the states of the given supervisor's workers while the supervisor is running.
     */
    public function refresh(string $supervisor): void;
}
