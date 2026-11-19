<?php

declare(strict_types=1);

namespace App\Domain\Media\UpdateMediaItem;

use App\Domain\Media\MediaItemId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class UpdateMediaItem extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private MediaItemId $mediaItemId,
        private ?Coordinate $coordinate,
        private ?SerializableDateTime $takenOn,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['mediaItemId']) || !is_string($payload['mediaItemId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "mediaItemId" is required.');
        }

        try {
            $mediaItemId = MediaItemId::fromString($payload['mediaItemId']);
        } catch (\InvalidArgumentException) {
            throw CouldNotDeserializeCommand::invalidPayload('The "mediaItemId" is invalid.');
        }

        $latitude = is_string($payload['latitude'] ?? null) ? trim($payload['latitude']) : '';
        $longitude = is_string($payload['longitude'] ?? null) ? trim($payload['longitude']) : '';

        $coordinate = null;
        if ('' !== $latitude || '' !== $longitude) {
            if ('' === $latitude || '' === $longitude) {
                throw CouldNotDeserializeCommand::invalidPayload('Both "latitude" and "longitude" are required.');
            }

            try {
                $coordinate = Coordinate::createFromLatAndLng(
                    latitude: Latitude::fromString($latitude),
                    longitude: Longitude::fromString($longitude),
                );
            } catch (\InvalidArgumentException) {
                throw CouldNotDeserializeCommand::invalidPayload('The "latitude" or "longitude" is invalid.');
            }
        }

        $takenOnValue = is_string($payload['takenOn'] ?? null) ? trim($payload['takenOn']) : '';

        $takenOn = null;
        if ('' !== $takenOnValue) {
            try {
                $takenOn = SerializableDateTime::fromLocalDateTimeString($takenOnValue);
            } catch (\InvalidArgumentException) {
                throw CouldNotDeserializeCommand::invalidPayload('The "takenOn" is invalid.');
            }
        }

        return new self(
            mediaItemId: $mediaItemId,
            coordinate: $coordinate,
            takenOn: $takenOn,
        );
    }

    public function getMediaItemId(): MediaItemId
    {
        return $this->mediaItemId;
    }

    public function getCoordinate(): ?Coordinate
    {
        return $this->coordinate;
    }

    public function getTakenOn(): ?SerializableDateTime
    {
        return $this->takenOn;
    }
}
