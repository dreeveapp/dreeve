<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollection;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Activity\Collection\DbalActivityCollectionRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

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
            ->build();
        $this->repository->add($activityCollection);

        $updated = $activityCollection
            ->withName('After')
            ->withDescription(null)
            ->withType(ActivityCollectionType::EVENT);
        $this->repository->update($updated);

        $this->assertEquals($updated, $this->repository->find($activityCollection->getId()));
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

        $this->repository = new DbalActivityCollectionRepository($this->getConnection());
    }
}
