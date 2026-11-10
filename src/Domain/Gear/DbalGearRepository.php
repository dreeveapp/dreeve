<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypes;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;
use Money\Currency;
use Money\Money;

final readonly class DbalGearRepository extends DbalRepository implements GearRepository
{
    public function __construct(
        Connection $connection,
        private EventBus $eventBus,
    ) {
        parent::__construct($connection);
    }

    public function add(Gear $gear): void
    {
        $sql = 'INSERT INTO Gear (gearId, createdOn, name, isRetired, `type`, localImagePath, purchasePriceAmount, purchasePriceCurrency, notes)
        VALUES (:gearId, :createdOn, :name, :isRetired, :type, :localImagePath, :purchasePriceAmount, :purchasePriceCurrency, :notes)';

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
            'notes' => $gear->getNotes(),
        ]);

        $this->eventBus->publishEvents($gear->getRecordedEvents());
    }

    public function update(Gear $gear): void
    {
        $sql = 'UPDATE Gear SET
                    name = :name,
                    isRetired = :isRetired,
                    `type` = :type,
                    localImagePath = :localImagePath,
                    purchasePriceAmount = :purchasePriceAmount,
                    purchasePriceCurrency = :purchasePriceCurrency,
                    notes = :notes
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
            'notes' => $gear->getNotes(),
        ]);

        $this->eventBus->publishEvents($gear->getRecordedEvents());
    }

    public function delete(Gear $gear): void
    {
        $this->connection->executeStatement(
            'DELETE FROM Gear WHERE gearId = :gearId',
            ['gearId' => $gear->getId()]
        );

        $this->eventBus->publishEvents($gear->getRecordedEvents());
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
        return (bool) $this->connection->executeQuery(
            'SELECT EXISTS(SELECT 1 FROM Gear INNER JOIN Activity ON Activity.gearId = Gear.gearId)'
        )->fetchOne();
    }

    public function find(GearId $gearId): Gear
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select(
            'Gear.*',
            'SUM(Activity.distance) AS totalDistance',
            'SUM(Activity.movingTimeInSeconds) AS totalMovingTime',
            'SUM(Activity.elevation) AS totalElevation',
            'SUM(Activity.calories) AS totalCalories',
            'COUNT(Activity.activityId) AS numberOfActivities',
        )
            ->from('Gear')
            ->leftJoin('Gear', 'Activity', 'Activity', 'Activity.gearId = Gear.gearId')
            ->andWhere('Gear.gearId = :gearId')
            ->setParameter('gearId', $gearId)
            ->groupBy('Gear.gearId');

        if (!$result = $queryBuilder->executeQuery()->fetchAssociative()) {
            throw new EntityNotFound(sprintf('Gear "%s" not found', $gearId));
        }

        return $this->hydrate(
            result: $result,
            activityTypes: $this->findActivityTypesPerGear($gearId)[(string) $gearId] ?? ActivityTypes::empty(),
        );
    }

    private function fetchFindAllResults(bool $onlyUsedGear): Gears
    {
        $results = $this->connection->executeQuery('
            SELECT Gear.*,
                   SUM(Activity.distance) as totalDistance,
                   SUM(Activity.movingTimeInSeconds) as totalMovingTime,
                   SUM(Activity.elevation) as totalElevation,
                   SUM(Activity.calories) as totalCalories,
                   COUNT(Activity.activityId) as numberOfActivities
            FROM Gear
            '.($onlyUsedGear ? 'INNER' : 'LEFT').' JOIN Activity ON Activity.gearId = Gear.gearId
            GROUP BY Gear.gearId, Gear.isRetired
            ORDER BY Gear.isRetired ASC, totalDistance DESC;
        ')->fetchAllAssociative();

        $activityTypesPerGear = $this->findActivityTypesPerGear();

        return Gears::fromArray(array_map(
            fn (array $result): Gear => $this->hydrate(
                result: $result,
                activityTypes: $activityTypesPerGear[$result['gearId']] ?? ActivityTypes::empty(),
            ),
            $results
        ));
    }

    /**
     * @return array<string, ActivityTypes>
     */
    private function findActivityTypesPerGear(?GearId $gearId = null): array
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('gearId', 'activityType')
            ->from('Activity')
            ->andWhere('gearId IS NOT NULL')
            ->groupBy('gearId', 'activityType')
            ->orderBy('COUNT(*)', 'DESC')
            ->addOrderBy('activityType', 'ASC');

        if ($gearId instanceof GearId) {
            $queryBuilder
                ->andWhere('gearId = :gearId')
                ->setParameter('gearId', $gearId);
        }

        $activityTypesPerGear = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $result) {
            $activityTypesPerGear[$result['gearId']] ??= ActivityTypes::empty();
            $activityTypesPerGear[$result['gearId']]->add(ActivityType::from($result['activityType']));
        }

        return $activityTypesPerGear;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result, ActivityTypes $activityTypes): Gear
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
            notes: $result['notes'] ?? null,
        );

        return $gear
            ->withActivityTypes($activityTypes)
            ->withMainActivityType($activityTypes->getFirst());
    }
}
