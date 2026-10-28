<?php

namespace Laravel\Horizon;

use JsonSerializable;

class WorkerState implements JsonSerializable
{
    /**
     * The worker is waiting for a job.
     */
    public const IDLE = 'idle';

    /**
     * The worker is running a job.
     */
    public const BUSY = 'busy';

    /**
     * Create a new worker state instance.
     */
    public function __construct(
        public int $pid,
        public string $supervisor,
        public string $connection,
        public ?string $queue,
        public string $status,
        public int $memory,
        public int $startedAt,
        public int $updatedAt,
        public ?RunningJob $job = null,
    ) {
    }

    /**
     * Get the attributes to store.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
