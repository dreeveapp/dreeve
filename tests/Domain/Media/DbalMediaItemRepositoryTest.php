<?php

declare(strict_types=1);

namespace App\Tests\Domain\Media;

use App\Domain\Media\DbalMediaItemRepository;
use App\Domain\Media\MediaItem;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class DbalMediaItemRepositoryTest extends ContainerTestCase
{
    private MediaItemRepository $mediaItemRepository;

    public function testAddAndFindByIds(): void
    {
        $mediaItem = MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('1'))
            ->withPath(MediaItemPath::fromString('activities/1.jpg'))
            ->withWidth(1200)
            ->withHeight(800)
            ->withCoordinate(Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('51.2'),
                longitude: Longitude::fromString('3.2'),
            ))
            ->withTakenOn(SerializableDateTime::fromString('2025-04-20 10:00:00'))
            ->build();
        $this->mediaItemRepository->add($mediaItem);
        $this->mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('2'))
            ->withPath(MediaItemPath::fromString('activities/2.m3u8'))
            ->build());

        $this->assertEquals(
            [
                [
                    'mediaItemId' => 'mediaItem-1',
                    'path' => 'activities/1.jpg',
                    'width' => 1200,
                    'height' => 800,
                    'coordinateLatitude' => 51.2,
                    'coordinateLongitude' => 3.2,
                    'takenOn' => '2025-04-20 10:00:00',
                ],
                [
                    'mediaItemId' => 'mediaItem-2',
                    'path' => 'activities/2.m3u8',
                    'width' => null,
                    'height' => null,
                    'coordinateLatitude' => null,
                    'coordinateLongitude' => null,
                    'takenOn' => null,
                ],
            ],
            $this->getConnection()->executeQuery('SELECT * FROM MediaItem ORDER BY mediaItemId')->fetchAllAssociative(),
        );
        $this->assertEquals(
            [$mediaItem],
            $this->mediaItemRepository->findByIds(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('1')]))->toArray(),
        );
        $this->assertEquals($mediaItem, $this->mediaItemRepository->find(MediaItemId::fromUnprefixed('1')));
    }

    public function testFindThrowsWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('MediaItem "mediaItem-1" not found'));

        $this->mediaItemRepository->find(MediaItemId::fromUnprefixed('1'));
    }

    public function testFindByIdsKeepsTheGivenOrder(): void
    {
        foreach (['a', 'b', 'c'] as $id) {
            $this->mediaItemRepository->add(MediaItemBuilder::fromDefaults()
                ->withMediaItemId(MediaItemId::fromUnprefixed($id))
                ->build());
        }

        $this->assertSame(
            ['mediaItem-c', 'mediaItem-a', 'mediaItem-b'],
            $this->mediaItemRepository->findByIds(MediaItemIds::fromArray([
                MediaItemId::fromUnprefixed('c'),
                MediaItemId::fromUnprefixed('missing'),
                MediaItemId::fromUnprefixed('a'),
                MediaItemId::fromUnprefixed('b'),
            ]))->map(static fn (MediaItem $mediaItem): string => (string) $mediaItem->getId()),
        );
        $this->assertTrue($this->mediaItemRepository->findByIds(MediaItemIds::empty())->isEmpty());
    }

    public function testUpdate(): void
    {
        $this->mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('1'))
            ->build());

        $updated = MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('1'))
            ->withPath(MediaItemPath::fromString('activities/updated.jpg'))
            ->withWidth(600)
            ->withHeight(900)
            ->withCoordinate(Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('45.1'),
                longitude: Longitude::fromString('6.3'),
            ))
            ->withTakenOn(SerializableDateTime::fromString('2025-05-01 08:30:00'))
            ->build();
        $this->mediaItemRepository->update($updated);

        $this->assertEquals(
            [$updated],
            $this->mediaItemRepository->findByIds(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('1')]))->toArray(),
        );
    }

    public function testDelete(): void
    {
        $mediaItem = MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('1'))
            ->build();
        $this->mediaItemRepository->add($mediaItem);
        $this->mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('2'))
            ->build());

        $this->mediaItemRepository->delete($mediaItem);

        $this->assertSame(
            ['mediaItem-2'],
            $this->mediaItemRepository->findByIds(MediaItemIds::fromArray([
                MediaItemId::fromUnprefixed('1'),
                MediaItemId::fromUnprefixed('2'),
            ]))->map(static fn (MediaItem $mediaItem): string => (string) $mediaItem->getId()),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaItemRepository = new DbalMediaItemRepository($this->getConnection());
    }
}
