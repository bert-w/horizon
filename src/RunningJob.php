<?php

namespace Laravel\Horizon;

use JsonSerializable;

class RunningJob implements JsonSerializable
{
    /**
     * Create a new running job instance.
     */
    public function __construct(
        public int|string|null $id,
        public ?string $uuid,
        public string $name,
        public string $queue,
        public int $attempts,
        public int $startedAt,
        public int $timeout,
        public ?int $deadline,
    ) {
    }

    /**
     * Create a running job from its stored attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): static
    {
        return new static(
            id: $attributes['id'],
            uuid: $attributes['uuid'],
            name: $attributes['name'],
            queue: $attributes['queue'],
            attempts: $attributes['attempts'],
            startedAt: $attributes['startedAt'],
            timeout: $attributes['timeout'],
            deadline: $attributes['deadline'],
        );
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
