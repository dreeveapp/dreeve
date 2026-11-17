<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;

final readonly class DbalMediaItemRepository extends DbalRepository implements MediaItemRepository
{
    public function add(MediaItem $mediaItem): void
    {
        $sql = 'INSERT INTO MediaItem (mediaItemId, path, width, height, coordinateLatitude, coordinateLongitude, takenOn)
                VALUES (:mediaItemId, :path, :width, :height, :coordinateLatitude, :coordinateLongitude, :takenOn)';

        $this->connection->executeStatement($sql, [
            'mediaItemId' => $mediaItem->getId(),
            'path' => $mediaItem->getPath()->toLocalPath(),
            'width' => $mediaItem->getWidth(),
            'height' => $mediaItem->getHeight(),
            'coordinateLatitude' => $mediaItem->getCoordinate()?->getLatitude()->toFloat(),
            'coordinateLongitude' => $mediaItem->getCoordinate()?->getLongitude()->toFloat(),
            'takenOn' => $mediaItem->getTakenOn(),
        ]);
    }

    public function update(MediaItem $mediaItem): void
    {
        $sql = 'UPDATE MediaItem SET
                    path = :path,
                    width = :width,
                    height = :height,
                    coordinateLatitude = :coordinateLatitude,
                    coordinateLongitude = :coordinateLongitude,
                    takenOn = :takenOn
                WHERE mediaItemId = :mediaItemId';

        $this->connection->executeStatement($sql, [
            'mediaItemId' => $mediaItem->getId(),
            'path' => $mediaItem->getPath()->toLocalPath(),
            'width' => $mediaItem->getWidth(),
            'height' => $mediaItem->getHeight(),
            'coordinateLatitude' => $mediaItem->getCoordinate()?->getLatitude()->toFloat(),
            'coordinateLongitude' => $mediaItem->getCoordinate()?->getLongitude()->toFloat(),
            'takenOn' => $mediaItem->getTakenOn(),
        ]);
    }

    public function delete(MediaItem $mediaItem): void
    {
        $this->connection->executeStatement(
            'DELETE FROM MediaItem WHERE mediaItemId = :mediaItemId',
            ['mediaItemId' => $mediaItem->getId()],
        );
    }

    public function find(MediaItemId $mediaItemId): MediaItem
    {
        if (!$result = $this->connection->executeQuery(
            'SELECT * FROM MediaItem WHERE mediaItemId = :mediaItemId',
            ['mediaItemId' => $mediaItemId],
        )->fetchAssociative()) {
            throw new EntityNotFound(sprintf('MediaItem "%s" not found', $mediaItemId));
        }

        return $this->hydrate($result);
    }

    public function findByIds(MediaItemIds $mediaItemIds): MediaItems
    {
        if ($mediaItemIds->isEmpty()) {
            return MediaItems::empty();
        }

        $results = $this->connection->executeQuery(
            'SELECT * FROM MediaItem WHERE mediaItemId IN (:mediaItemIds)',
            ['mediaItemIds' => array_map(strval(...), $mediaItemIds->toArray())],
            ['mediaItemIds' => ArrayParameterType::STRING],
        )->fetchAllAssociative();

        $resultsById = array_column($results, null, 'mediaItemId');

        return MediaItems::fromArray(array_map(
            fn (string $mediaItemId): MediaItem => $this->hydrate($resultsById[$mediaItemId]),
            array_values(array_filter(
                array_map(strval(...), $mediaItemIds->toArray()),
                static fn (string $mediaItemId): bool => isset($resultsById[$mediaItemId]),
            )),
        ));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): MediaItem
    {
        return MediaItem::fromState(
            mediaItemId: MediaItemId::fromString($result['mediaItemId']),
            path: MediaItemPath::fromLocalPath($result['path']),
            width: null !== $result['width'] ? (int) $result['width'] : null,
            height: null !== $result['height'] ? (int) $result['height'] : null,
            coordinate: Coordinate::createFromOptionalLatAndLng(
                Latitude::fromOptionalString((string) $result['coordinateLatitude']),
                Longitude::fromOptionalString((string) $result['coordinateLongitude']),
            ),
            takenOn: null !== $result['takenOn'] ? SerializableDateTime::fromString($result['takenOn']) : null,
        );
    }
}
