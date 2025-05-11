<?php

namespace Laravel\Horizon\Listeners;

use Carbon\CarbonImmutable;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Events\MasterSupervisorLooped;

class TrimTags
{
    /**
     * The last time the monitored tags were trimmed.
     */
    protected ?CarbonImmutable $lastTrimmed = null;

    /**
     * The tag repository implementation.
     *
     * @var \Laravel\Horizon\Contracts\TagRepository
     */
    protected $tags;

    public function __construct(TagRepository $tags)
    {
        $this->tags = $tags;
    }

    public function handle(MasterSupervisorLooped $event): void
    {
        $now = CarbonImmutable::now();

        if (is_null($this->lastTrimmed) || $this->lastTrimmed->addMinutes($this->tags->trimFrequency())->lte($now)) {
            $this->tags->trim();
            $this->lastTrimmed = $now;
        }
    }
}
