<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\AddActivityImage;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\AddActivityImage\AddActivityImage;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Media\MediaItems;
use App\Domain\Media\NewMediaItem;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\String\Path;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
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
                ->withMediaItemIds(MediaItemId::fromUnprefixed('existing'))
                ->build(),
            rawData: [],
        ));

        $content = (string) file_get_contents(dirname(__DIR__, 2).'/Media/Metadata/fixtures/image-with-exif.jpg');

        $this->commandBus->dispatch(new AddActivityImage(
            activityId: ActivityId::fromUnprefixed('1'),
            mediaItemId: MediaItemId::fromUnprefixed('new'),
            newMediaItem: new NewMediaItem(
                filename: Path::fromString('photo.jpg'),
                content: $content,
            ),
        ));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertEquals(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('existing'), MediaItemId::fromUnprefixed('new')]), $activity->getMediaItemIds());
        $this->assertSame($content, $this->fileStorage->read('activities/new.jpg'));
        $this->assertEquals(
            MediaItems::fromArray([
                MediaItemBuilder::fromDefaults()
                    ->withMediaItemId(MediaItemId::fromUnprefixed('new'))
                    ->withPath(MediaItemPath::fromString('activities/new.jpg'))
                    ->withWidth(3)
                    ->withHeight(4)
                    ->withCoordinate(Coordinate::createFromLatAndLng(
                        latitude: Latitude::fromString('-22.9519'),
                        longitude: Longitude::fromString('-43.2105'),
                    ))
                    ->withTakenOn(SerializableDateTime::fromString('2025-06-14 08:42:17'))
                    ->build(),
            ]),
            $this->getContainer()->get(MediaItemRepository::class)->findByIds(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('new')])),
        );
        $this->assertSame('existing', $this->fileStorage->read('activities/existing.png'));
    }

    public function testHandleThrowsWhenActivityNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Activity "activity-999" not found'));

        $this->commandBus->dispatch(new AddActivityImage(
            activityId: ActivityId::fromUnprefixed('999'),
            mediaItemId: MediaItemId::fromUnprefixed('new'),
            newMediaItem: new NewMediaItem(
                filename: Path::fromString('photo.jpg'),
                content: 'new-content',
            ),
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
