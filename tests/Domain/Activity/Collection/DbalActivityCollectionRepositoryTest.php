<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Collection\ActivityCollection;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Activity\Collection\DbalActivityCollectionRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Infrastructure\Eventing\SpyEventBus;

class DbalActivityCollectionRepositoryTest extends ContainerTestCase
{
    private DbalActivityCollectionRepository $repository;

    public function testAddAndFind(): void
    {
        $activityCollection = ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withName('Transalp 2025')
            ->withDescription('Four stages from Austria to Italy.')
            ->withType(ActivityCollectionType::EVENT)
            ->withActivityIds(ActivityIds::fromArray([
                ActivityId::fromUnprefixed('2'),
                ActivityId::fromUnprefixed('1'),
            ]))
            ->withCoverImagePath('/files/activity-collections/cover.jpg')
            ->build();
        $this->repository->add($activityCollection);

        $this->assertEquals($activityCollection, $this->repository->find($activityCollection->getId()));
        $this->assertEquals(
            [
                [
                    'activityCollectionId' => 'activityCollection-1',
                    'name' => 'Transalp 2025',
                    'description' => 'Four stages from Austria to Italy.',
                    'type' => 'event',
                    'activityIds' => '["activity-2","activity-1"]',
                    'coverImagePath' => '/files/activity-collections/cover.jpg',
                    'createdOn' => '2025-04-20 10:00:00',
                ],
            ],
            $this->getConnection()->executeQuery('SELECT * FROM ActivityCollection')->fetchAllAssociative(),
        );
    }

    public function testFindThrowsWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('ActivityCollection "activityCollection-1" not found'));

        $this->repository->find(ActivityCollectionId::fromUnprefixed('1'));
    }

    public function testFindAllOrdersByCreatedOnDescending(): void
    {
        $this->repository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('a'))
            ->withCreatedOn(SerializableDateTime::fromString('2025-01-01 10:00:00'))
            ->build());
        $this->repository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('b'))
            ->withCreatedOn(SerializableDateTime::fromString('2025-03-01 10:00:00'))
            ->build());
        $this->repository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('c'))
            ->withCreatedOn(SerializableDateTime::fromString('2025-02-01 10:00:00'))
            ->build());

        $this->assertSame(
            ['activityCollection-b', 'activityCollection-c', 'activityCollection-a'],
            $this->repository->findAll()->map(static fn (ActivityCollection $activityCollection): string => (string) $activityCollection->getId())
        );
    }

    public function testUpdate(): void
    {
        $activityCollection = ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withName('Before')
            ->withDescription('Before')
            ->withType(ActivityCollectionType::TRIP)
            ->withCoverImagePath('/files/activity-collections/before.jpg')
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1')]))
            ->build();
        $this->repository->add($activityCollection);

        $updated = $activityCollection
            ->withName('After')
            ->withDescription(null)
            ->withType(ActivityCollectionType::EVENT)
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('2'), ActivityId::fromUnprefixed('3')]))
            ->withCoverImagePath(null);
        $this->repository->update($updated);

        $this->assertEquals($updated, $this->repository->find($activityCollection->getId()));
    }

    public function testFindForActivity(): void
    {
        $this->repository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('a'))
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1'), ActivityId::fromUnprefixed('2')]))
            ->build());
        $this->repository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('b'))
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('3')]))
            ->build());
        $this->repository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('c'))
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('2')]))
            ->build());

        $this->assertSame(
            ['activityCollection-a', 'activityCollection-c'],
            $this->repository->findForActivity(ActivityId::fromUnprefixed('2'))->map(static fn (ActivityCollection $activityCollection): string => (string) $activityCollection->getId())
        );
    }

    public function testHasActivityCollectionsWithActivities(): void
    {
        $this->assertFalse($this->repository->hasActivityCollectionsWithActivities());

        $this->repository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('deleted')]))
            ->build());
        $this->assertFalse($this->repository->hasActivityCollectionsWithActivities());

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->withActivityId(ActivityId::fromUnprefixed('1'))->build(),
            [],
        ));
        $this->repository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('2'))
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1')]))
            ->build());
        $this->assertTrue($this->repository->hasActivityCollectionsWithActivities());
    }

    public function testDelete(): void
    {
        $activityCollection = ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->build();
        $this->repository->add($activityCollection);
        $this->repository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('2'))
            ->build());

        $activityCollection->delete();
        $this->repository->delete($activityCollection);

        $this->assertSame(
            ['activityCollection-2'],
            $this->repository->findAll()->map(static fn (ActivityCollection $activityCollection): string => (string) $activityCollection->getId())
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DbalActivityCollectionRepository(
            $this->getConnection(),
            new SpyEventBus(),
        );
    }
}
