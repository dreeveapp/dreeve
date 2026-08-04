<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Activity\ActivityIds;
use App\Infrastructure\Repository\DbalRepository;
use Doctrine\DBAL\ArrayParameterType;

final readonly class DbalGearIdRepository extends DbalRepository implements GearIdRepository
{
    public function findAll(): GearIds
    {
        return $this->fetchGearIds('SELECT gearId FROM Gear');
    }

    public function findRetired(): GearIds
    {
        return $this->fetchGearIds('SELECT gearId FROM Gear WHERE isRetired = 1');
    }

    public function findUniqueStravaGearIds(?ActivityIds $restrictToActivityIds): GearIds
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('DISTINCT JSON_EXTRACT(data, "$.gear_id") as stravaGearId')
            ->from('Activity')
            ->andWhere('stravaGearId IS NOT NULL');

        if ($restrictToActivityIds && !$restrictToActivityIds->isEmpty()) {
            $queryBuilder->andWhere('activityId IN (:activityIds)');
            $queryBuilder->setParameter(
                key: 'activityIds',
                value: array_map(strval(...), $restrictToActivityIds->toArray()),
                type: ArrayParameterType::STRING
            );
        }

        return GearIds::fromArray(array_map(
            GearId::fromUnprefixed(...),
            $queryBuilder->executeQuery()->fetchFirstColumn(),
        ));
    }

    private function fetchGearIds(string $query): GearIds
    {
        return GearIds::fromArray(array_map(
            GearId::fromString(...),
            $this->connection->executeQuery($query)->fetchFirstColumn(),
        ));
    }
}
