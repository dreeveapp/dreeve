<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection\AddActivityCollection;

use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Activity\Collection\AddActivityCollection\AddActivityCollection;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class AddActivityCollectionCommandHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $this->getContainer()->get(CommandBus::class)->dispatch(AddActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'name' => '  Mallorca  ',
            'type' => 'trip',
            'description' => '  Spring cycling week  ',
        ]));

        $activityCollection = $this->getContainer()->get(ActivityCollectionRepository::class)->find(ActivityCollectionId::fromUnprefixed('1'));
        $this->assertSame('Mallorca', $activityCollection->getName());
        $this->assertSame('Spring cycling week', $activityCollection->getDescription());
        $this->assertSame(ActivityCollectionType::TRIP, $activityCollection->getType());
        $this->assertEquals(ActivityIds::empty(), $activityCollection->getActivityIds());
        $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:15:04'), $activityCollection->getCreatedOn());
    }

    public function testHandleWithoutDescription(): void
    {
        $this->getContainer()->get(CommandBus::class)->dispatch(AddActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'name' => 'Mallorca',
            'type' => 'event',
            'description' => '   ',
        ]));

        $activityCollection = $this->getContainer()->get(ActivityCollectionRepository::class)->find(ActivityCollectionId::fromUnprefixed('1'));
        $this->assertNull($activityCollection->getDescription());
        $this->assertSame(ActivityCollectionType::EVENT, $activityCollection->getType());
    }
}
