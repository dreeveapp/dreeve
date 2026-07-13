<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\UpdateActivity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\UpdateActivity\UpdateActivity;
use App\Domain\Gear\GearId;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use League\Flysystem\FilesystemOperator;

class UpdateActivityCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private ActivityRepository $activityRepository;
    private FilesystemOperator $fileStorage;

    public function testHandle(): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withName('Original activity')
                ->withSportType(SportType::RIDE)
                ->build(),
            rawData: [],
        ));

        $this->commandBus->dispatch(UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'Updated activity',
            'sportType' => 'Run',
            'description' => 'Updated description',
            'deviceName' => 'Garmin Edge',
            'gearId' => 'gear-1',
            'isCommute' => 'true',
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertSame('Updated activity', $activity->getOriginalName());
        $this->assertSame(SportType::RUN, $activity->getSportType());
        $this->assertSame('Updated description', $activity->getDescription());
        $this->assertSame('Garmin Edge', $activity->getDeviceName());
        $this->assertEquals(GearId::fromUnprefixed('1'), $activity->getGearId());
        $this->assertTrue($activity->isCommute());
    }

    public function testHandleLeavesImagesUntouchedWhenImagesKeyIsMissing(): void
    {
        $this->fileStorage->write('activities/existing.png', 'binary');
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withLocalImagePaths('files/activities/existing.png')
                ->build(),
            rawData: [],
        ));

        $this->commandBus->dispatch(UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'Updated activity',
            'sportType' => 'Ride',
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertSame(['/files/activities/existing.png'], $activity->getLocalImagePaths());
        $this->assertTrue($this->fileStorage->fileExists('activities/existing.png'));
    }

    public function testHandleRemovesImages(): void
    {
        $this->fileStorage->write('activities/existing.png', 'binary');
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withLocalImagePaths('files/activities/existing.png')
                ->build(),
            rawData: [],
        ));

        $this->commandBus->dispatch(UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'Updated activity',
            'sportType' => 'Ride',
            'images' => json_encode([
                ['status' => 'removed', 'path' => '/files/activities/existing.png'],
            ]),
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertSame([], $activity->getLocalImagePaths());
        $this->assertFalse($this->fileStorage->fileExists('activities/existing.png'));
    }

    public function testHandleLeavesImagesUntouchedWhenNothingChanged(): void
    {
        $this->fileStorage->write('activities/existing.png', 'binary');
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withLocalImagePaths('files/activities/existing.png')
                ->build(),
            rawData: [],
        ));

        // Unchanged images are not part of the payload, an empty list means nothing was added or removed.
        $this->commandBus->dispatch(UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'Updated activity',
            'sportType' => 'Ride',
            'images' => '[]',
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertSame(['/files/activities/existing.png'], $activity->getLocalImagePaths());
        $this->assertTrue($this->fileStorage->fileExists('activities/existing.png'));
    }

    public function testHandleAddsNewAndRemovesImagesWhileKeepingTheRest(): void
    {
        $this->fileStorage->write('activities/keep.png', 'keep');
        $this->fileStorage->write('activities/drop.png', 'drop');
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withLocalImagePaths('files/activities/keep.png', 'files/activities/drop.png')
                ->build(),
            rawData: [],
        ));

        $this->commandBus->dispatch(UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'Updated activity',
            'sportType' => 'Ride',
            'images' => json_encode([
                ['status' => 'removed', 'path' => '/files/activities/drop.png'],
                ['status' => 'new', 'filename' => 'new.jpg', 'content' => base64_encode('new-content')],
            ]),
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $localImagePaths = $activity->getLocalImagePaths();
        $this->assertCount(2, $localImagePaths);
        $this->assertSame('/files/activities/keep.png', $localImagePaths[0]);
        $this->assertStringEndsWith('.jpg', $localImagePaths[1]);

        // The dropped image was deleted, the kept one is untouched and the new one was written.
        $this->assertFalse($this->fileStorage->fileExists('activities/drop.png'));
        $this->assertTrue($this->fileStorage->fileExists('activities/keep.png'));
        $newRelativePath = ltrim((string) preg_replace('#^/files/#', '', $localImagePaths[1]), '/');
        $this->assertSame('new-content', $this->fileStorage->read($newRelativePath));
    }

    public function testHandleDoesNotDeleteFilesThatDoNotBelongToTheActivity(): void
    {
        $this->fileStorage->write('activities/someone-else.png', 'binary');
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->build(),
            rawData: [],
        ));

        $this->commandBus->dispatch(UpdateActivity::fromPayload([
            'activityId' => 'activity-1',
            'name' => 'Updated activity',
            'sportType' => 'Ride',
            'images' => json_encode([
                ['status' => 'removed', 'path' => '/files/activities/someone-else.png'],
            ]),
        ]));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertSame([], $activity->getLocalImagePaths());
        // A removed path that was never attached to this activity may not delete the file from disk.
        $this->assertTrue($this->fileStorage->fileExists('activities/someone-else.png'));
    }

    public function testHandleThrowsWhenActivityNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Activity "activity-999" not found'));

        $this->commandBus->dispatch(UpdateActivity::fromPayload([
            'activityId' => 'activity-999',
            'name' => 'Updated activity',
            'sportType' => 'Run',
        ]));
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
