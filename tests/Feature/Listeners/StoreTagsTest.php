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
        $tagRepository = m::mock(TagRepository::class);

        $tagRepository->shouldReceive('addTemporary')->once()->with(120, '1', ['testtag'])->andReturn([]);
        $tagRepository->shouldReceive('ttl')->once()->andReturn(120);

        $this->instance(TagRepository::class, $tagRepository);

        $this->app->make(Dispatcher::class)->dispatch(
            new JobPushed('{"id":"1","displayName":"displayName","tags":["testtag"]}')
        );
    }
}

