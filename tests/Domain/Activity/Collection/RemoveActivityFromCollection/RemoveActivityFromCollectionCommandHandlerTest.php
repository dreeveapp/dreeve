<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection\RemoveActivityFromCollection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\RemoveActivityFromCollection\RemoveActivityFromCollection;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;

class RemoveActivityFromCollectionCommandHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $activityCollectionRepository = $this->getContainer()->get(ActivityCollectionRepository::class);
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1'), ActivityId::fromUnprefixed('2')]))
            ->build());
        $commandBus = $this->getContainer()->get(CommandBus::class);

        $commandBus->dispatch(RemoveActivityFromCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'activityId' => 'activity-1',
        ]));
        $commandBus->dispatch(RemoveActivityFromCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'activityId' => 'activity-999',
        ]));

        $this->assertEquals(
            ActivityIds::fromArray([ActivityId::fromUnprefixed('2')]),
            $activityCollectionRepository->find(ActivityCollectionId::fromUnprefixed('1'))->getActivityIds(),
        );
    }

    public function testHandleThrowsWhenActivityCollectionNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('ActivityCollection "activityCollection-999" not found'));

        $this->getContainer()->get(CommandBus::class)->dispatch(RemoveActivityFromCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-999',
            'activityId' => 'activity-1',
        ]));
    }
}
