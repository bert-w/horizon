<?php

namespace Laravel\Horizon\Tests\Feature\Jobs;

use Laravel\Horizon\Contracts\WorkerStateRepository;
use Laravel\Horizon\WorkerState;

class WorkerStateJob
{
    public static ?WorkerState $state = null;

    public int $timeout = 45;

    public function handle(WorkerStateRepository $states): void
    {
        static::$state = $states->find('supervisor-1', getmypid());
    }
}
