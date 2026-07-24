<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\String\Name;

final readonly class DbalSegmentRepository extends DbalRepository implements SegmentRepository
{
    public function add(Segment $segment): void
    {
        $sql = 'INSERT INTO Segment (segmentId, name, sportType, distance, maxGradient, isFavourite, 
                     deviceName, climbCategory, countryCode, detailsHaveBeenImported, polyline,
                     startingCoordinateLatitude, startingCoordinateLongitude, averageGradient) 
                VALUES (:segmentId, :name, :sportType, :distance, :maxGradient, :isFavourite, 
                        :deviceName, :climbCategory, :countryCode, :detailsHaveBeenImported, :polyline,
                        :startingCoordinateLatitude, :startingCoordinateLongitude, :averageGradient)';

        $this->connection->executeStatement($sql, [
            'segmentId' => $segment->getId(),
            'name' => $segment->getOriginalName(),
            'sportType' => $segment->getSportType()->value,
            'distance' => $segment->getDistance()->toMeter()->toInt(),
            'maxGradient' => $segment->getMaxGradient(),
            'isFavourite' => (int) $segment->isFavourite(),
            'deviceName' => $segment->getDeviceName(),
            'climbCategory' => $segment->getClimbCategory(),
            'countryCode' => $segment->getCountryCode(),
            'detailsHaveBeenImported' => (int) $segment->detailsHaveBeenImported(),
            'polyline' => null,
            'startingCoordinateLatitude' => null,
            'startingCoordinateLongitude' => null,
            'averageGradient' => $segment->getAverageGradient(),
        ]);
    }

    public function update(Segment $segment): void
    {
        $sql = 'UPDATE Segment SET 
                    isFavourite = :isFavourite,
                    detailsHaveBeenImported = :detailsHaveBeenImported,
                    polyline = :polyline,
                    startingCoordinateLatitude = :startingCoordinateLatitude,
                    startingCoordinateLongitude = :startingCoordinateLongitude
                    WHERE segmentId = :segmentId';

        $polyline = $segment->getPolyline();
        $startingCoordinate = $polyline?->getStartingCoordinate();
        $this->connection->executeStatement($sql, [
            'segmentId' => $segment->getId(),
            'isFavourite' => (int) $segment->isFavourite(),
            'detailsHaveBeenImported' => (int) $segment->detailsHaveBeenImported(),
            'polyline' => $polyline,
            'startingCoordinateLatitude' => $startingCoordinate?->getLatitude()->toFloat(),
            'startingCoordinateLongitude' => $startingCoordinate?->getLongitude()->toFloat(),
        ]);
    }

    public function find(SegmentId $segmentId): Segment
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('Segment')
            ->andWhere('segmentId = :segmentId')
            ->setParameter('segmentId', $segmentId);

        if (!$result = $queryBuilder->executeQuery()->fetchAssociative()) {
            throw new EntityNotFound(sprintf('Segment "%s" not found', $segmentId));
        }

        return $this->hydrate($result);
    }

    public function findAll(Pagination $pagination): Segments
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*', '(SELECT COUNT(*) FROM SegmentEffort WHERE SegmentEffort.segmentId = Segment.segmentId) as countCompleted')
            ->from('Segment')
            ->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getLimit())
            ->orderBy('countCompleted', 'DESC');

        return Segments::fromArray(array_map(
            $this->hydrate(...),
            $queryBuilder->executeQuery()->fetchAllAssociative()
        ));
    }

    public function findSegmentsIdsMissingDetails(): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('segmentId')
            ->from('Segment')
            ->andWhere('detailsHaveBeenImported = 0');

        return array_map(
            SegmentId::fromString(...),
            $queryBuilder->executeQuery()->fetchFirstColumn()
        );
    }

    public function count(): int
    {
        return (int) $this->connection->executeQuery('SELECT COUNT(*) FROM Segment')->fetchOne();
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): Segment
    {
        return Segment::fromState(
            segmentId: SegmentId::fromString($result['segmentId']),
            name: Name::fromString($result['name']),
            sportType: SportType::from($result['sportType']),
            distance: Meter::from($result['distance'])->toKilometer(),
            maxGradient: $result['maxGradient'],
            isFavourite: (bool) $result['isFavourite'],
            climbCategory: $result['climbCategory'],
            deviceName: $result['deviceName'],
            countryCode: $result['countryCode'],
            detailsHaveBeenImported: (bool) $result['detailsHaveBeenImported'],
            polyline: EncodedPolyline::fromOptionalString($result['polyline']),
            startingCoordinate: Coordinate::createFromOptionalLatAndLng(
                Latitude::fromOptionalString((string) $result['startingCoordinateLatitude']),
                Longitude::fromOptionalString((string) $result['startingCoordinateLongitude'])
            ),
            averageGradient: isset($result['averageGradient']) ? (float) $result['averageGradient'] : null,
        );
    }

    public function deleteOrphaned(): void
    {
        $this->connection->executeStatement('DELETE FROM Segment WHERE NOT EXISTS(
            SELECT 1 FROM SegmentEffort WHERE SegmentEffort.segmentId = Segment.segmentId
        )');
    }
}
