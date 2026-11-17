<?php

namespace App\Tests\Domain\Activity\Media;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Media\ActivityBasedActivityMediaRepository;
use App\Domain\Activity\Media\ActivityMediaItem;
use App\Domain\Activity\Media\ActivityMediaRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Media\ImageOrientation;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
use League\Flysystem\FilesystemOperator;

class ActivityBasedActivityMediaRepositoryTest extends ContainerTestCase
{
    private ActivityMediaRepository $activityMediaRepository;
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
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withSportType(SportType::RUN)
                ->withStartDateTime(SerializableDateTime::fromString('2024-03-01 00:00:00'))
                ->withMediaItemIds(MediaItemId::fromUnprefixed('test'))
                ->build(),
            []
        ));
        $this->getContainer()->get(MediaItemRepository::class)->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('test'))
            ->withPath(MediaItemPath::fromLocalPath('files/activities/test.jpg'))
            ->build()
        );

        $this->assertEquals(
            ActivityId::fromUnprefixed('3'),
            $this->activityMediaRepository->findRandomFor(
                sportTypes: SportTypes::thatSupportImagesForStravaRewind(),
                years: Years::fromArray([Year::fromInt(2024)]),
            )->getActivity()->getId()
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
                ->build(),
            []
        ));

        $this->expectExceptionObject(new EntityNotFound('Random image not found'));

        $this->activityMediaRepository->findRandomFor(
            sportTypes: SportTypes::thatSupportImagesForStravaRewind(),
            years: Years::fromArray([Year::fromInt(2024)]),
        );
    }

    public function testCount(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::RUN)
                ->withTotalImageCount(3)
                ->withMediaItemIds(MediaItemId::fromUnprefixed('run'))
                ->build(),
            []
        ));
        $this->getContainer()->get(MediaItemRepository::class)->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('run'))
            ->withPath(MediaItemPath::fromLocalPath('files/activities/run.jpg'))
            ->withWidth(600)
            ->withHeight(800)
            ->withCoordinate(Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('51.2'),
                longitude: Longitude::fromString('3.2'),
            ))
            ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withSportType(SportType::RIDE)
                ->withTotalImageCount(4)
                ->withMediaItemIds(MediaItemId::fromUnprefixed('ride'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withSportType(SportType::WALK)
                ->withTotalImageCount(5)
                ->build(),
            []
        ));

        $this->assertEquals(12, $this->activityMediaRepository->count());

        $this->getContainer()->get(SettingsRepository::class)->saveGroup(SettingsGroup::APPEARANCE, [
            'photos' => [
                'hidePhotosForSportTypes' => [SportType::RIDE->value, SportType::WALK->value],
            ],
        ]);

        $this->assertEquals(3, $this->activityMediaRepository->count());
        $activityMediaItems = $this->activityMediaRepository->findAll();
        $this->assertEquals(
            ['/files/activities/run.jpg'],
            $activityMediaItems->map(static fn (ActivityMediaItem $mediaItem): string => $mediaItem->getImageUrl()),
        );
        $this->assertEquals(
            [ImageOrientation::PORTRAIT],
            $activityMediaItems->map(static fn (ActivityMediaItem $mediaItem): ImageOrientation => $mediaItem->getOrientation()),
        );
        $this->assertEquals(
            [Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('51.2'),
                longitude: Longitude::fromString('3.2'),
            )],
            $activityMediaItems->map(static fn (ActivityMediaItem $mediaItem): ?Coordinate => $mediaItem->getCoordinate()),
        );
    }

    public function testDeleteForActivity(): void
    {
        $this->fileStorage->write('activities/one.png', 'one');
        $this->fileStorage->write('activities/two.png', 'two');
        foreach (['one', 'two'] as $name) {
            $this->getContainer()->get(MediaItemRepository::class)->add(MediaItemBuilder::fromDefaults()
                ->withMediaItemId(MediaItemId::fromUnprefixed($name))
                ->withPath(MediaItemPath::fromLocalPath('files/activities/'.$name.'.png'))
                ->build()
            );
        }

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withMediaItemIds(MediaItemId::fromUnprefixed('one'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withMediaItemIds(MediaItemId::fromUnprefixed('two'))
                ->build(),
            []
        ));

        $this->activityMediaRepository->deleteForActivity(ActivityId::fromUnprefixed('1'));

        $this->assertFalse($this->fileStorage->fileExists('activities/one.png'));
        $this->assertTrue($this->fileStorage->fileExists('activities/two.png'));
    }

    public function testDeleteForActivityWhenActivityDoesNotExist(): void
    {
        $this->expectNotToPerformAssertions();

        $this->activityMediaRepository->deleteForActivity(ActivityId::fromUnprefixed('1'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityMediaRepository = new ActivityBasedActivityMediaRepository(
            $this->getConnection(),
            $this->getContainer()->get(ActivityRepository::class),
            $this->fileStorage = $this->getContainer()->get('file.storage'),
            $this->getContainer()->get(SettingsRepository::class),
            $this->getContainer()->get(MediaItemRepository::class),
        );
    }
}
