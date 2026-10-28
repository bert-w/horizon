<?php

namespace Laravel\Horizon\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\WorkerStateRepository;
use Laravel\Horizon\Tests\Feature\Jobs\WorkerStateJob;
use Laravel\Horizon\Tests\IntegrationTest;
use Laravel\Horizon\WorkerState;

class WorkerStateTest extends IntegrationTest
{
    protected function tearDown(): void
    {
        WorkerStateJob::$state = null;

        parent::tearDown();
    }

    public function test_worker_records_the_job_it_is_running(): void
    {
        Queue::push(new WorkerStateJob);

        Artisan::call('horizon:work', [
            'connection' => 'redis',
            '--once' => true,
            '--supervisor' => 'supervisor-1',
            '--timeout' => 60,
        ]);

        $state = WorkerStateJob::$state;

        $this->assertSame(WorkerState::BUSY, $state->status);
        $this->assertSame(getmypid(), $state->pid);
        $this->assertSame(WorkerStateJob::class, $state->job->name);
        $this->assertSame(45, $state->job->timeout);
        $this->assertSame($state->job->startedAt + 45, $state->job->deadline);

        $state = app(WorkerStateRepository::class)->find('supervisor-1', getmypid());

        $this->assertSame(WorkerState::IDLE, $state->status);
        $this->assertNull($state->job);
    }

    public function test_worker_states_can_be_retrieved(): void
    {
        $states = app(WorkerStateRepository::class);

        $states->put($this->workerState('supervisor-1', 123, WorkerState::BUSY));
        $states->put($this->workerState('supervisor-1', 456));

        $this->assertSame(WorkerState::BUSY, $states->find('supervisor-1', 123)->status);
        $this->assertEqualsCanonicalizing([123, 456], array_keys($states->forSupervisor('supervisor-1')));

        $states->put($this->workerState('supervisor-1', 123));
        $states->forget('supervisor-1', 456);

        $this->assertSame(WorkerState::IDLE, $states->find('supervisor-1', 123)->status);
        $this->assertNull($states->find('supervisor-1', 456));
        $this->assertNull($states->find('supervisor-2', 123));
    }

    public function test_worker_states_expire_unless_refreshed(): void
    {
        app(WorkerStateRepository::class)->put($this->workerState('supervisor-1', 123));

        $ttl = Redis::connection('horizon')->ttl('supervisor:supervisor-1:workers');

        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(30, $ttl);
    }
}
