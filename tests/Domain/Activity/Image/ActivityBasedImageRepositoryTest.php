<?php

namespace App\Tests\Domain\Activity\Image;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\EnrichedActivities;
use App\Domain\Activity\Image\ActivityBasedImageRepository;
use App\Domain\Activity\Image\ImageRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use League\Flysystem\FilesystemOperator;

class ActivityBasedImageRepositoryTest extends ContainerTestCase
{
    private ImageRepository $imageRepository;
    private FilesystemOperator $fileStorage;

    public function testFindRandomFor(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('0'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-03-01 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::VIRTUAL_ROW)
                ->withStartDateTime(SerializableDateTime::fromString('2024-03-01 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withSportType(SportType::RUN)
                ->withStartDateTime(SerializableDateTime::fromString('2024-03-01 00:00:00'))
                ->withoutLocalImagePaths()
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withSportType(SportType::RUN)
                ->withStartDateTime(SerializableDateTime::fromString('2024-03-01 00:00:00'))
                ->withLocalImagePaths('test')
                ->build(),
            []
        ));

        $this->assertEquals(
            ActivityId::fromUnprefixed('3'),
            $this->imageRepository->findRandomFor(
                sportTypes: SportTypes::thatSupportImagesForStravaRewind(),
                years: Years::fromArray([Year::fromInt(2024)]),
            )->getActivityId()
        );
    }

    public function testFindRandomForItShouldThrow(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('0'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-03-01 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::VIRTUAL_ROW)
                ->withStartDateTime(SerializableDateTime::fromString('2024-03-01 00:00:00'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withSportType(SportType::RUN)
                ->withStartDateTime(SerializableDateTime::fromString('2024-03-01 00:00:00'))
                ->withoutLocalImagePaths()
                ->build(),
            []
        ));

        $this->expectExceptionObject(new EntityNotFound('Random image not found'));

        $this->imageRepository->findRandomFor(
            sportTypes: SportTypes::thatSupportImagesForStravaRewind(),
            years: Years::fromArray([Year::fromInt(2024)]),
        );
    }

    public function testDeleteForActivity(): void
    {
        $this->fileStorage->write('activities/one.png', 'one');
        $this->fileStorage->write('activities/two.png', 'two');

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withLocalImagePaths('files/activities/one.png')
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withLocalImagePaths('files/activities/two.png')
                ->build(),
            []
        ));

        $this->imageRepository->deleteForActivity(ActivityId::fromUnprefixed('1'));

        $this->assertFalse($this->fileStorage->fileExists('activities/one.png'));
        $this->assertTrue($this->fileStorage->fileExists('activities/two.png'));
    }

    public function testDeleteForActivityWhenActivityDoesNotExist(): void
    {
        $this->expectNotToPerformAssertions();

        $this->imageRepository->deleteForActivity(ActivityId::fromUnprefixed('1'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->imageRepository = new ActivityBasedImageRepository(
            $this->getContainer()->get(EnrichedActivities::class),
            $this->getContainer()->get(ActivityRepository::class),
            $this->fileStorage = $this->getContainer()->get('file.storage'),
            $this->getContainer()->get(SettingsRepository::class),
            KernelProjectDir::fromString('var/www')
        );
    }
}
