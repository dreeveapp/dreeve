<?php

declare(strict_types=1);

namespace App\Tests\Domain\Media\UpdateMediaItem;

use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Media\UpdateMediaItem\UpdateMediaItem;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Media\MediaItemBuilder;

class UpdateMediaItemCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private MediaItemRepository $mediaItemRepository;

    public function testHandle(): void
    {
        $this->mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('1'))
            ->build());

        $this->commandBus->dispatch(UpdateMediaItem::fromPayload([
            'mediaItemId' => 'mediaItem-1',
            'latitude' => '51.2194',
            'longitude' => '4.4025',
            'takenOn' => '2025-04-20T10:15',
        ]));

        $mediaItem = $this->mediaItemRepository->find(MediaItemId::fromUnprefixed('1'));
        $this->assertEquals(
            Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('51.2194'),
                longitude: Longitude::fromString('4.4025'),
            ),
            $mediaItem->getCoordinate(),
        );
        $this->assertEquals(SerializableDateTime::fromString('2025-04-20 10:15:00'), $mediaItem->getTakenOn());

        $this->commandBus->dispatch(UpdateMediaItem::fromPayload([
            'mediaItemId' => 'mediaItem-1',
            'latitude' => '',
            'longitude' => '',
            'takenOn' => '',
        ]));

        $mediaItem = $this->mediaItemRepository->find(MediaItemId::fromUnprefixed('1'));
        $this->assertNull($mediaItem->getCoordinate());
        $this->assertNull($mediaItem->getTakenOn());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->mediaItemRepository = $this->getContainer()->get(MediaItemRepository::class);
    }
}
