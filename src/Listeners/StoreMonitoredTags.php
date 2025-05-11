<?php

namespace Laravel\Horizon\Listeners;

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
        $tags = $event->payload->tags();

        if (config('horizon.monitor_all_tags')) {
            $this->tags->add($event->payload->id(), $tags);

            return;
        }

        $monitoring = $this->tags->monitored($tags);

        if (! empty($monitoring)) {
            $this->tags->addTemporary(
                max(
                    config('horizon.trim.pending', 0),
                    config('horizon.trim.completed', 0),
                    config('horizon.trim.failed', 0)
                ) ?? 2880,
                $event->payload->id(), $monitoring,
            );
        }
    }
}
