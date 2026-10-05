<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\AddActivityImage;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\AddActivityImage\AddActivityImage;
use App\Domain\Image\ImagePath;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use League\Flysystem\FilesystemOperator;

class AddActivityImageCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private ActivityRepository $activityRepository;
    private FilesystemOperator $fileStorage;

    public function testHandle(): void
    {
        $this->fileStorage->write('activities/existing.png', 'existing');
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withLocalImagePaths('files/activities/existing.png')
                ->build(),
            rawData: [],
        ));

        $this->commandBus->dispatch(new AddActivityImage(
            activityId: ActivityId::fromUnprefixed('1'),
            path: ImagePath::fromFileSystemPath('activities/new.jpg'),
            content: 'new-content',
        ));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertSame(['/files/activities/existing.png', '/files/activities/new.jpg'], $activity->getLocalImagePaths());
        $this->assertSame(2, $activity->getTotalImageCount());
        $this->assertSame('new-content', $this->fileStorage->read('activities/new.jpg'));
        $this->assertSame('existing', $this->fileStorage->read('activities/existing.png'));
    }

    public function testHandleThrowsWhenActivityNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Activity "activity-999" not found'));

        $this->commandBus->dispatch(new AddActivityImage(
            activityId: ActivityId::fromUnprefixed('999'),
            path: ImagePath::fromFileSystemPath('activities/new.jpg'),
            content: 'new-content',
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
