<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection\DeleteActivityCollection;

use App\Domain\Activity\Collection\ActivityCollection;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\DeleteActivityCollection\DeleteActivityCollection;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;

class DeleteActivityCollectionCommandHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $fileStorage = $this->getContainer()->get('file.storage');
        $fileStorage->write('activity-collections/cover.jpg', 'content');
        $activityCollectionRepository = $this->getContainer()->get(ActivityCollectionRepository::class);
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withCoverImagePath('/files/activity-collections/cover.jpg')
            ->build());
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('2'))
            ->build());

        $this->getContainer()->get(CommandBus::class)->dispatch(DeleteActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-1',
        ]));

        $this->assertEquals(
            [ActivityCollectionId::fromUnprefixed('2')],
            $activityCollectionRepository->findAll()->map(static fn (ActivityCollection $activityCollection): ActivityCollectionId => $activityCollection->getId()),
        );
        $this->assertFalse($fileStorage->fileExists('activity-collections/cover.jpg'));
    }

    public function testHandleThrowsWhenActivityCollectionNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('ActivityCollection "activityCollection-999" not found'));

        $this->getContainer()->get(CommandBus::class)->dispatch(DeleteActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-999',
        ]));
    }
}
