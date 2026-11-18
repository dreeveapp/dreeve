<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection\DeleteActivityCollection;

use App\Domain\Activity\Collection\ActivityCollection;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\DeleteActivityCollection\DeleteActivityCollection;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;

class DeleteActivityCollectionCommandHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $fileStorage = $this->getContainer()->get('file.storage');
        $fileStorage->write('activity-collections/cover.jpg', 'content');
        $mediaItemRepository = $this->getContainer()->get(MediaItemRepository::class);
        $mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('cover'))
            ->withPath(MediaItemPath::fromString('activity-collections/cover.jpg'))
            ->build());
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
        $this->assertTrue($mediaItemRepository->findByIds(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('cover')]))->isEmpty());
    }

    public function testHandleThrowsWhenActivityCollectionNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('ActivityCollection "activityCollection-999" not found'));

        $this->getContainer()->get(CommandBus::class)->dispatch(DeleteActivityCollection::fromPayload([
            'activityCollectionId' => 'activityCollection-999',
        ]));
    }

    public function testFromPayloadThrowsWhenActivityCollectionIdIsMissing(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('An "activityCollectionId" is required.'));

        DeleteActivityCollection::fromPayload(['activityCollectionId' => '   ']);
    }
}
