<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection\AddActivityToCollection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\AddActivityToCollection\AddActivityToCollection;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;

class AddActivityToCollectionCommandHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $activityRepository->add(ActivityWithRawData::fromState(ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->build(), []));
        $activityRepository->add(ActivityWithRawData::fromState(ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('2'))
            ->build(), []));
        $activityCollectionRepository = $this->getContainer()->get(ActivityCollectionRepository::class);
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1')]))
            ->build());
        $commandBus = $this->getContainer()->get(CommandBus::class);

        $commandBus->dispatch(AddActivityToCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'activityId' => 'Morning ride (activity-2)',
        ]));
        $commandBus->dispatch(AddActivityToCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'activityId' => 'activity-1',
        ]));

        $this->assertEquals(
            ActivityIds::fromArray([ActivityId::fromUnprefixed('1'), ActivityId::fromUnprefixed('2')]),
            $activityCollectionRepository->find(ActivityCollectionId::fromUnprefixed('1'))->getActivityIds(),
        );
    }

    public function testHandleThrowsWhenActivityDoesNotExist(): void
    {
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->build());

        $this->expectExceptionObject(CouldNotProcessCommand::withReason('The selected activity does not exist.'));

        $this->getContainer()->get(CommandBus::class)->dispatch(AddActivityToCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
            'activityId' => 'activity-999',
        ]));
    }

    public function testHandleThrowsWhenActivityCollectionNotFound(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->build(), []));

        $this->expectExceptionObject(new EntityNotFound('ActivityCollection "activityCollection-999" not found'));

        $this->getContainer()->get(CommandBus::class)->dispatch(AddActivityToCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-999',
            'activityId' => 'activity-1',
        ]));
    }
}
