<?php

namespace Laravel\Horizon\Listeners;

use Carbon\CarbonImmutable;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Events\MasterSupervisorLooped;

class TrimTags
{
    /**
     * How many minutes to wait in between each trim.
     *
     * @var int
     */
    public int $frequency;

    /**
     * The last time the tags were trimmed.
     */
    protected ?CarbonImmutable $lastTrimmed = null;

    /**
     * The tag repository implementation.
     *
     * @var \Laravel\Horizon\Contracts\TagRepository
     */
    protected TagRepository $tags;

    public function __construct(TagRepository $tags)
    {
        $this->tags = $tags;

        $this->frequency = config('horizon.trim.tags', 1440);
    }

    public function handle(MasterSupervisorLooped $event): void
    {
        $now = CarbonImmutable::now();

        if (is_null($this->lastTrimmed) || $this->lastTrimmed->addMinutes($this->frequency)->lte($now)) {
            $this->tags->prune();
            $this->lastTrimmed = $now;
        }
    }
}
