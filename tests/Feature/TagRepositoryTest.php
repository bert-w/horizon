<?php

namespace Laravel\Horizon\Tests\Feature;

use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Repositories\RedisTagRepository;
use Laravel\Horizon\Tests\IntegrationTest;

class TagRepositoryTest extends IntegrationTest
{
    public function test_pagination_of_job_ids_can_be_accomplished()
    {
        $repo = resolve(TagRepository::class);

        for ($i = 0; $i < 50; $i++) {
            $repo->add((string) $i, ['tag']);
        }

        $results = $repo->paginate('tag', 0, 25);

        $this->assertCount(25, $results);
        $this->assertSame('49', $results[0]);
        $this->assertSame('25', $results[24]);

        $results = $repo->paginate('tag', last(array_keys($results)) + 1, 25);

        $this->assertCount(25, $results);
        $this->assertSame('24', $results[25]);
        $this->assertSame('0', $results[49]);
    }

    public function test_prune_ttl_can_be_overridden()
    {
        $repo = resolve(TagRepository::class);

        config()->set('horizon.tags.ttl', 60);
        $this->assertEquals($repo->ttl(), 60);

        // Test fallback to job trim times.
        config()->set('horizon.tags.ttl', null);
        $this->assertEquals($repo->ttl(), 10080);
    }

    public function test_expired_tags_are_pruned()
    {
        config()->set('horizon.tags.ttl', 60);

        /** @var RedisTagRepository $repo */
        $repo = resolve(TagRepository::class);

        $jobId1 = '93075c0b-b918-4ea7-b6a4-59f73fbbdcb3';
        $jobId2 = '0b4a6210-78d8-43b7-b34f-745c16c886d9';

        $repo->addTemporary($repo->ttl(), $jobId1, ['tagone', 'tagtwo']);
        $repo->addTemporary($repo->ttl(), $jobId2, ['tagone', 'tagthree']);

        $this->assertEquals([$jobId1, $jobId2], $repo->jobs('tagone'));
        $this->assertEquals([$jobId1], $repo->jobs('tagtwo'));
        $this->assertEquals([$jobId2], $repo->jobs('tagthree'));

        $this->travel(10)->minutes();
        $repo->prune();
        // Expect no changes.
        $this->assertEquals([$jobId1, $jobId2], $repo->jobs('tagone'));
        $this->assertEquals([$jobId1], $repo->jobs('tagtwo'));
        $this->assertEquals([$jobId2], $repo->jobs('tagthree'));

        $this->travel(1)->hour();
        $repo->prune();
        // Expect removed tags.
        $this->assertEquals([], $repo->jobs('tagone'));
        $this->assertEquals([], $repo->jobs('tagtwo'));
        $this->assertEquals([], $repo->jobs('tagthree'));
    }
}
