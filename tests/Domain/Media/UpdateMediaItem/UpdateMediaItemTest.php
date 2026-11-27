<?php

declare(strict_types=1);

namespace App\Tests\Domain\Media\UpdateMediaItem;

use App\Domain\Media\MediaItemId;
use App\Domain\Media\UpdateMediaItem\UpdateMediaItem;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateMediaItemTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = UpdateMediaItem::fromPayload([
            'mediaItemId' => 'mediaItem-1',
            'latitude' => ' 51.2194 ',
            'longitude' => '4.4025',
            'takenOn' => '2025-04-20T10:15:30',
        ]);

        $this->assertEquals(MediaItemId::fromUnprefixed('1'), $command->getMediaItemId());
        $this->assertEquals(
            Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('51.2194'),
                longitude: Longitude::fromString('4.4025'),
            ),
            $command->getCoordinate(),
        );
        $this->assertEquals(SerializableDateTime::fromString('2025-04-20 10:15:30'), $command->getTakenOn());

        $command = UpdateMediaItem::fromPayload([
            'mediaItemId' => 'mediaItem-1',
            'latitude' => '',
            'longitude' => ' ',
            'takenOn' => '',
        ]);

        $this->assertNull($command->getCoordinate());
        $this->assertNull($command->getTakenOn());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        UpdateMediaItem::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing mediaItemId' => [
            [],
            'A "mediaItemId" is required.',
        ];
        yield 'mediaItemId without prefix' => [
            ['mediaItemId' => 'activity-1'],
            'The "mediaItemId" is invalid.',
        ];
        yield 'only a latitude' => [
            ['mediaItemId' => 'mediaItem-1', 'latitude' => '51.2194', 'longitude' => ''],
            'Both "latitude" and "longitude" are required.',
        ];
        yield 'only a longitude' => [
            ['mediaItemId' => 'mediaItem-1', 'longitude' => '4.4025'],
            'Both "latitude" and "longitude" are required.',
        ];
        yield 'latitude out of range' => [
            ['mediaItemId' => 'mediaItem-1', 'latitude' => '91', 'longitude' => '4.4025'],
            'The "latitude" or "longitude" is invalid.',
        ];
        yield 'longitude out of range' => [
            ['mediaItemId' => 'mediaItem-1', 'latitude' => '51.2194', 'longitude' => '-181'],
            'The "latitude" or "longitude" is invalid.',
        ];
        yield 'non-numeric latitude' => [
            ['mediaItemId' => 'mediaItem-1', 'latitude' => 'north', 'longitude' => '4.4025'],
            'The "latitude" or "longitude" is invalid.',
        ];
        yield 'takenOn in the wrong format' => [
            ['mediaItemId' => 'mediaItem-1', 'takenOn' => '20/04/2025 10:15'],
            'The "takenOn" is invalid.',
        ];
        yield 'takenOn with an impossible date' => [
            ['mediaItemId' => 'mediaItem-1', 'takenOn' => '2025-02-30T10:15'],
            'The "takenOn" is invalid.',
        ];
    }
}
