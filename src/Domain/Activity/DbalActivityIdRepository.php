<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearType;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DbalActivityIdRepository implements ActivityIdRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function count(): int
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('COUNT(activityId)')
            ->from('Activity');

        return (int) $queryBuilder->executeQuery()->fetchOne();
    }

    public function findAll(): ActivityIds
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('activityId')
            ->from('Activity')
            ->orderBy('startDateTime', 'DESC');

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $queryBuilder->executeQuery()->fetchFirstColumn()
        ));
    }

    public function findAllImportedFromStravaApi(): ActivityIds
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('activityId')
            ->from('Activity')
            ->andWhere('importSource = :importSource')
            ->setParameter('importSource', ImportSource::STRAVA_API->value)
            ->orderBy('startDateTime', 'DESC');

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $queryBuilder->executeQuery()->fetchFirstColumn()
        ));
    }

    public function hasImportedFromStravaApi(): bool
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('1')
            ->from('Activity')
            ->andWhere('importSource = :importSource')
            ->setParameter('importSource', ImportSource::STRAVA_API->value)
            ->setMaxResults(1);

        return (bool) $queryBuilder->executeQuery()->fetchOne();
    }

    public function findAllWithoutStravaGear(): ActivityIds
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('activityId')
            ->from('Activity')
            ->andWhere('gearId IS NULL OR gearId NOT IN (SELECT gearId FROM Gear WHERE type = :gearType)')
            ->setParameter('gearType', GearType::IMPORTED->value)
            ->orderBy('startDateTime', 'DESC');

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $queryBuilder->executeQuery()->fetchFirstColumn()
        ));
    }

    public function hasForSportTypes(SportTypes $sportTypes): bool
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('COUNT(activityId)')
            ->from('Activity')
            ->andWhere('sportType IN (:sportTypes)')
            ->setParameter(
                key: 'sportTypes',
                value: array_map(fn (SportType $sportType) => $sportType->value, $sportTypes->toArray()),
                type: ArrayParameterType::STRING
            );

        return (bool) $queryBuilder->executeQuery()->fetchOne();
    }

    public function findMarkedForDeletion(): ActivityIds
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('activityId')
            ->from('Activity')
            ->where('markedForDeletion = 1')
            ->orderBy('startDateTime', 'DESC')
            ->addOrderBy('activityId', 'ASC');

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $queryBuilder->executeQuery()->fetchFirstColumn(),
        ));
    }
}
