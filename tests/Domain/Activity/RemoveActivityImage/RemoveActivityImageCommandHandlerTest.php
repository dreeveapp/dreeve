<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\RemoveActivityImage;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Image\ActivityImageId;
use App\Domain\Activity\RemoveActivityImage\RemoveActivityImage;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
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
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withLocalImagePaths('files/activities/keep.png', 'files/activities/drop.jpg')
                ->build(),
            rawData: [],
        ));

        $this->commandBus->dispatch(new RemoveActivityImage(
            activityId: ActivityId::fromUnprefixed('1'),
            activityImageId: ActivityImageId::fromUnprefixed('drop'),
        ));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertSame(['/files/activities/keep.png'], $activity->getLocalImagePaths());
        $this->assertSame(1, $activity->getTotalImageCount());
        $this->assertFalse($this->fileStorage->fileExists('activities/drop.jpg'));
        $this->assertTrue($this->fileStorage->fileExists('activities/keep.png'));
    }

    public function testHandleThrowsWhenImageDoesNotBelongToTheActivity(): void
    {
        $this->fileStorage->write('activities/someone-else.png', 'binary');
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withLocalImagePaths('files/activities/keep.png')
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
            $this->assertSame(['/files/activities/keep.png'], $this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getLocalImagePaths());
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
