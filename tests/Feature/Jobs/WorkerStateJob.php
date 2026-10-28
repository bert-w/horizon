<?php

namespace Laravel\Horizon\Tests\Feature\Jobs;

use Laravel\Horizon\Contracts\WorkerStateRepository;
use stdClass;

class WorkerStateJob
{
    public static ?stdClass $state = null;

    public int $timeout = 45;

    public function handle(WorkerStateRepository $states): void
    {
        static::$state = $states->find('supervisor-1', getmypid());
    }
}
