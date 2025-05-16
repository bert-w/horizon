<?php

namespace Laravel\Horizon\Listeners;

use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Events\JobPushed;

class StoreMonitoredTags
{
    /**
     * The tag repository implementation.
     *
     * @var \Laravel\Horizon\Contracts\TagRepository
     */
    public $tags;

    /**
     * Create a new listener instance.
     *
     * @param  \Laravel\Horizon\Contracts\TagRepository  $tags
     * @return void
     */
    public function __construct(TagRepository $tags)
    {
        $this->tags = $tags;
    }

    /**
     * Handle the event.
     *
     * @param  \Laravel\Horizon\Events\JobPushed  $event
     * @return void
     */
    public function handle(JobPushed $event)
    {
        if (!$monitored = config('horizon.monitor_tags', true)) {
            return;
        }

        if (is_array($monitored)) {
            $tags = array_filter(
                $event->payload->tags(),
                fn (string $tag) => Str::is($monitored, $tag),
            );
        } else {
            $tags = $event->payload->tags();
        }

        $this->tags->addTemporary(
            $this->tags->trimFrequency() ?? 2880,
            $event->payload->id(),
            $tags,
        );
    }
}
