<?php

namespace Laravel\Horizon\Tests\Feature\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Events\JobPushed;
use Laravel\Horizon\Tests\IntegrationTest;
use Mockery as m;

class StoreTagsTest extends IntegrationTest
{
    protected function tearDown(): void
    {
        parent::tearDown();

        m::close();
    }

    public function test_tags_should_be_stored_on_job_push(): void
    {
        config()->set('horizon.trim.pending', 120);
        config()->set('horizon.trim.completed', 120);
        // Tags should be stored for the longest time defined in the config, so we define 240 here.
        config()->set('horizon.trim.failed', 240);

        $tagRepository = m::mock(TagRepository::class);

        $tagRepository->shouldReceive('addTemporary')->once()->with(240, '1', ['testtag'])->andReturn([]);

        $this->instance(TagRepository::class, $tagRepository);

        $this->app->make(Dispatcher::class)->dispatch(
            new JobPushed('{"id":"1","displayName":"displayName","tags":["testtag"]}')
        );
    }
}

