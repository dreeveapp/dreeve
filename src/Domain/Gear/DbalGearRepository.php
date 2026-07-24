<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypes;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;
use Money\Currency;
use Money\Money;

final readonly class DbalGearRepository extends DbalRepository implements GearRepository
{
    public function add(Gear $gear): void
    {
        $sql = 'INSERT INTO Gear (gearId, createdOn, name, isRetired, `type`, localImagePath, purchasePriceAmount, purchasePriceCurrency)
        VALUES (:gearId, :createdOn, :name, :isRetired, :type, :localImagePath, :purchasePriceAmount, :purchasePriceCurrency)';

        $purchasePrice = $gear->getPurchasePrice();
        $this->connection->executeStatement($sql, [
            'gearId' => $gear->getId(),
            'createdOn' => $gear->getCreatedOn(),
            'name' => $gear->getOriginalName(),
            'isRetired' => (int) $gear->isRetired(),
            'type' => $gear->getType()->value,
            'localImagePath' => $gear->getLocalImagePath(),
            'purchasePriceAmount' => $purchasePrice?->getAmount(),
            'purchasePriceCurrency' => $purchasePrice?->getCurrency()->getCode(),
        ]);
    }

    public function update(Gear $gear): void
    {
        $sql = 'UPDATE Gear SET
                    name = :name,
                    isRetired = :isRetired,
                    `type` = :type,
                    localImagePath = :localImagePath,
                    purchasePriceAmount = :purchasePriceAmount,
                    purchasePriceCurrency = :purchasePriceCurrency
                    WHERE gearId = :gearId';

        $purchasePrice = $gear->getPurchasePrice();
        $this->connection->executeStatement($sql, [
            'gearId' => $gear->getId(),
            'name' => $gear->getOriginalName(),
            'isRetired' => (int) $gear->isRetired(),
            'type' => $gear->getType()->value,
            'localImagePath' => $gear->getLocalImagePath(),
            'purchasePriceAmount' => $purchasePrice?->getAmount(),
            'purchasePriceCurrency' => $purchasePrice?->getCurrency()->getCode(),
        ]);
    }

    public function findAll(): Gears
    {
        return $this->fetchFindAllResults(onlyUsedGear: false);
    }

    public function findAllUsed(): Gears
    {
        return $this->fetchFindAllResults(onlyUsedGear: true);
    }

    public function hasGear(): bool
    {
        return !$this->findAllUsed()->isEmpty();
    }

    public function find(GearId $gearId): Gear
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('Gear.*', 'SUM(Activity.distance) AS totalDistance')
            ->from('Gear')
            ->leftJoin('Gear', 'Activity', 'Activity', 'Activity.gearId = Gear.gearId')
            ->andWhere('Gear.gearId = :gearId')
            ->setParameter('gearId', $gearId)
            ->groupBy('Gear.gearId');

        if (!$result = $queryBuilder->executeQuery()->fetchAssociative()) {
            throw new EntityNotFound(sprintf('Gear "%s" not found', $gearId));
        }

        return $this->hydrate($result);
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

    private function fetchFindAllResults(bool $onlyUsedGear): Gears
    {
        $results = $this->connection->executeQuery('
            SELECT Gear.*,
                   SUM(Activity.distance) as totalDistance,
                   SUM(Activity.movingTimeInSeconds) as totalMovingTime,
                   SUM(Activity.elevation) as totalElevation,
                   SUM(Activity.calories) as totalCalories,
                   COUNT(Activity.activityId) as numberOfActivities,
                   GROUP_CONCAT(DISTINCT Activity.activityType) AS activityTypes
            FROM Gear
            '.($onlyUsedGear ? 'INNER' : 'LEFT').' JOIN Activity ON Activity.gearId = Gear.gearId
            GROUP BY Gear.gearId, Gear.isRetired
            ORDER BY Gear.isRetired ASC, totalDistance DESC;
        ')->fetchAllAssociative();

        return Gears::fromArray(array_map(
            $this->hydrate(...),
            $results
        ));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): Gear
    {
        $purchasePrice = null;
        $purchasePriceCurrency = (string) ($result['purchasePriceCurrency'] ?? '');
        if (isset($result['purchasePriceAmount']) && '' !== $purchasePriceCurrency) {
            $purchasePrice = new Money(
                amount: (int) $result['purchasePriceAmount'],
                currency: new Currency($purchasePriceCurrency)
            );
        }

        $gear = Gear::fromState(
            gearId: GearId::fromString($result['gearId']),
            distanceInMeter: Meter::from((float) $result['totalDistance']),
            createdOn: SerializableDateTime::fromString($result['createdOn']),
            name: $result['name'],
            isRetired: (bool) $result['isRetired'],
            type: GearType::from($result['type']),
            localImagePath: $result['localImagePath'] ?? null,
            movingTime: Seconds::from((float) ($result['totalMovingTime'] ?? 0)),
            elevation: Meter::from((float) ($result['totalElevation'] ?? 0)),
            numberOfActivities: (int) ($result['numberOfActivities'] ?? 0),
            totalCalories: (int) ($result['totalCalories'] ?? 0),
            purchasePrice: $purchasePrice,
        );

        $activityTypes = ActivityTypes::empty();
        if (!empty($result['activityTypes'])) {
            $activityTypes = ActivityTypes::fromArray(array_map(
                ActivityType::from(...),
                explode(',', (string) $result['activityTypes'])
            ));
        }

        return $gear->withActivityTypes($activityTypes);
    }
}
