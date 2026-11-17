<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\RemoveActivityImage;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Media\ActivityImageId;
use App\Domain\Activity\RemoveActivityImage\RemoveActivityImage;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Media\MediaItems;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
use League\Flysystem\FilesystemOperator;

class RemoveActivityImageCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private ActivityRepository $activityRepository;
    private FilesystemOperator $fileStorage;

    public function testHandle(): void
    {
        $this->fileStorage->write('activities/keep.png', 'keep');
        $this->fileStorage->write('activities/drop.jpg', 'drop');
        $mediaItemRepository = $this->getContainer()->get(MediaItemRepository::class);
        $keep = MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('keep'))
            ->withPath(MediaItemPath::fromString('activities/keep.png'))
            ->build();
        $mediaItemRepository->add($keep);
        $mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('drop'))
            ->withPath(MediaItemPath::fromString('activities/drop.jpg'))
            ->build());
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withMediaItemIds(MediaItemId::fromUnprefixed('keep'), MediaItemId::fromUnprefixed('drop'))
                ->build(),
            rawData: [],
        ));

        $this->commandBus->dispatch(new RemoveActivityImage(
            activityId: ActivityId::fromUnprefixed('1'),
            activityImageId: ActivityImageId::fromUnprefixed('drop'),
        ));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertEquals(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('keep')]), $activity->getMediaItemIds());
        $this->assertSame(1, $activity->getTotalImageCount());
        $this->assertFalse($this->fileStorage->fileExists('activities/drop.jpg'));
        $this->assertTrue($this->fileStorage->fileExists('activities/keep.png'));
        $this->assertEquals(
            MediaItems::fromArray([$keep]),
            $mediaItemRepository->findByIds(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('keep'), MediaItemId::fromUnprefixed('drop')])),
        );
    }

    public function testHandleThrowsWhenImageDoesNotBelongToTheActivity(): void
    {
        $this->fileStorage->write('activities/someone-else.png', 'binary');
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withMediaItemIds(MediaItemId::fromUnprefixed('keep'))
                ->build(),
            rawData: [],
        ));

        $this->expectExceptionObject(new EntityNotFound('Image "activityImage-someone-else" not found'));

        try {
            $this->commandBus->dispatch(new RemoveActivityImage(
                activityId: ActivityId::fromUnprefixed('1'),
                activityImageId: ActivityImageId::fromUnprefixed('someone-else'),
            ));
        } finally {
            $this->assertEquals(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('keep')]), $this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getMediaItemIds());
            $this->assertTrue($this->fileStorage->fileExists('activities/someone-else.png'));
        }
    }

    public function testHandleThrowsWhenActivityNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Activity "activity-999" not found'));

        $this->commandBus->dispatch(new RemoveActivityImage(
            activityId: ActivityId::fromUnprefixed('999'),
            activityImageId: ActivityImageId::fromUnprefixed('drop'),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->fileStorage = $this->getContainer()->get('file.storage');
    }
}
