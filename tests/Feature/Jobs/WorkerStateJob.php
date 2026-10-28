<?php

namespace Laravel\Horizon\Tests\Feature\Jobs;

use Laravel\Horizon\Contracts\WorkerStateRepository;

class WorkerStateJob
{
    public static $state;

    public $timeout = 45;

    public function handle(WorkerStateRepository $states)
    {
        static::$state = $states->find(getmypid());
    }
}
