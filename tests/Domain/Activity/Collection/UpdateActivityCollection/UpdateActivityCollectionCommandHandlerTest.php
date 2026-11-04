<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection\UpdateActivityCollection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Activity\Collection\UpdateActivityCollection\UpdateActivityCollection;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;

class UpdateActivityCollectionCommandHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $activityCollectionRepository = $this->getContainer()->get(ActivityCollectionRepository::class);
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withName('Mallorca')
            ->withDescription('Spring cycling week')
            ->withType(ActivityCollectionType::TRIP)
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1')]))
            ->build());

        $this->getContainer()->get(CommandBus::class)->dispatch(UpdateActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'name' => '  Transalp 2025  ',
            'type' => 'event',
            'description' => '   ',
        ]));

        $activityCollection = $activityCollectionRepository->find(ActivityCollectionId::fromUnprefixed('1'));
        $this->assertSame('Transalp 2025', $activityCollection->getName());
        $this->assertNull($activityCollection->getDescription());
        $this->assertSame(ActivityCollectionType::EVENT, $activityCollection->getType());
        $this->assertEquals(ActivityIds::fromArray([ActivityId::fromUnprefixed('1')]), $activityCollection->getActivityIds());
    }

    public function testHandleThrowsWhenActivityCollectionNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('ActivityCollection "activityCollection-999" not found'));

        $this->getContainer()->get(CommandBus::class)->dispatch(UpdateActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-999',
            'name' => 'Mallorca',
            'type' => 'trip',
        ]));
    }
}
