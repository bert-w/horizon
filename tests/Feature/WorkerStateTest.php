<?php

namespace Laravel\Horizon\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\WorkerStateRepository;
use Laravel\Horizon\Tests\Feature\Jobs\WorkerStateJob;
use Laravel\Horizon\Tests\IntegrationTest;

class WorkerStateTest extends IntegrationTest
{
    protected function tearDown(): void
    {
        WorkerStateJob::$state = null;

        parent::tearDown();
    }

    public function test_worker_records_the_job_it_is_running()
    {
        Queue::push(new WorkerStateJob);

        Artisan::call('horizon:work', [
            'connection' => 'redis',
            '--once' => true,
            '--supervisor' => 'supervisor-1',
            '--timeout' => 60,
        ]);

        $state = WorkerStateJob::$state;

        $this->assertSame('busy', $state->status);
        $this->assertSame(getmypid(), $state->pid);
        $this->assertSame(WorkerStateJob::class, $state->job->name);
        $this->assertSame(45, $state->job->timeout);
        $this->assertSame($state->job->started_at + 45, $state->job->deadline);

        $this->assertSame('idle', app(WorkerStateRepository::class)->find('supervisor-1', getmypid())->status);
    }

    public function test_worker_states_can_be_retrieved()
    {
        $states = app(WorkerStateRepository::class);

        $states->put('supervisor-1', 123, ['pid' => 123, 'status' => 'busy']);
        $states->put('supervisor-1', 456, ['pid' => 456, 'status' => 'idle']);

        $this->assertSame('busy', $states->find('supervisor-1', 123)->status);
        $this->assertSame([123, 456], array_keys($states->forSupervisor('supervisor-1')));

        $states->put('supervisor-1', 123, ['pid' => 123, 'status' => 'idle']);
        $states->forget('supervisor-1', 456);

        $this->assertSame('idle', $states->find('supervisor-1', 123)->status);
        $this->assertNull($states->find('supervisor-1', 456));
        $this->assertNull($states->find('supervisor-2', 123));
    }
}
