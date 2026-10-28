<?php

namespace Laravel\Horizon\Console;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Console\WorkCommand as BaseWorkCommand;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Events\WorkerStopping;
use Laravel\Horizon\Contracts\WorkerStateRepository;

class WorkCommand extends BaseWorkCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'horizon:work
                            {connection? : The name of the queue connection to work}
                            {--name=default : The name of the worker}
                            {--delay=0 : The number of seconds to delay failed jobs (Deprecated)}
                            {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
                            {--max-jobs=0 : The number of jobs to process before stopping}
                            {--max-time=0 : The maximum number of seconds the worker should run}
                            {--daemon : Run the worker in daemon mode (Deprecated)}
                            {--force : Force the worker to run even in maintenance mode}
                            {--memory=128 : The memory limit in megabytes}
                            {--once : Only process the next job on the queue}
                            {--stop-when-empty : Stop when the queue is empty}
                            {--stop-when-empty-for=0 : Stop when no jobs have been processed for the given number of seconds}
                            {--queue= : The names of the queues to work}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--rest=0 : Number of seconds to rest between jobs}
                            {--supervisor= : The name of the supervisor the worker belongs to}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=0 : Number of times to attempt a job before logging it failed}
                            {--json : Output the queue worker information as JSON}';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (config('horizon.fast_termination')) {
            ignore_user_abort(true);
        }

        $this->recordWorkerState();

        parent::handle();
    }

    /**
     * Keep the state of this worker in step with the job it is running.
     */
    protected function recordWorkerState(): void
    {
        if (! $supervisor = $this->option('supervisor')) {
            return;
        }

        $states = $this->laravel->make(WorkerStateRepository::class);

        $worker = [
            'pid' => getmypid(),
            'supervisor' => $supervisor,
            'connection' => $this->argument('connection') ?: $this->laravel['config']['queue.default'],
            'queue' => $this->option('queue'),
            'started_at' => CarbonImmutable::now()->getTimestamp(),
        ];

        $record = function (string $status, ?array $job = null) use ($states, $worker): void {
            $states->put($worker['supervisor'], $worker['pid'], $worker + [
                'status' => $status,
                'memory' => memory_get_usage(true),
                'updated_at' => CarbonImmutable::now()->getTimestamp(),
                'job' => $job,
            ]);
        };

        $record('idle');

        $this->laravel['events']->listen(JobProcessing::class, function (JobProcessing $event) use ($record): void {
            $startedAt = CarbonImmutable::now()->getTimestamp();

            // The same timeout the worker enforces: the job's own, else the worker's...
            $timeout = $event->job->timeout() ?? (int) $this->option('timeout');

            $record('busy', [
                'id' => $event->job->getJobId(),
                'uuid' => $event->job->uuid(),
                'name' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'attempts' => $event->job->attempts(),
                'started_at' => $startedAt,
                'timeout' => $timeout,
                'deadline' => $timeout > 0 ? $startedAt + $timeout : null,
            ]);
        });

        $this->laravel['events']->listen(
            [JobProcessed::class, JobReleasedAfterException::class, JobFailed::class],
            fn () => $record('idle')
        );

        $this->laravel['events']->listen(
            WorkerStopping::class,
            fn () => $states->forget($worker['supervisor'], $worker['pid'])
        );
    }
}
