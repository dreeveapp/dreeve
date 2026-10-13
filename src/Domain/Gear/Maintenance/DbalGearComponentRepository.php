<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Connection;

final readonly class DbalGearComponentRepository extends DbalRepository implements GearComponentRepository
{
    public function __construct(
        Connection $connection,
        private EventBus $eventBus,
    ) {
        parent::__construct($connection);
    }

    public function add(GearComponent $gearComponent): void
    {
        $sql = 'INSERT INTO GearComponent (gearComponentId, label, localImagePath, purchasePriceAmount, purchasePriceCurrency, isRetired, notes, installations, maintenanceTasks)
                VALUES (:gearComponentId, :label, :localImagePath, :purchasePriceAmount, :purchasePriceCurrency, :isRetired, :notes, :installations, :maintenanceTasks)';

        $this->connection->executeStatement($sql, [
            'gearComponentId' => (string) $gearComponent->getId(),
            'label' => (string) $gearComponent->getLabel(),
            'localImagePath' => $gearComponent->getLocalImagePath(),
            'purchasePriceAmount' => $gearComponent->getPurchasePrice()?->getAmount(),
            'purchasePriceCurrency' => $gearComponent->getPurchasePrice()?->getCurrency()->getCode(),
            'isRetired' => (int) $gearComponent->isRetired(),
            'notes' => $gearComponent->getNotes(),
            'installations' => Json::encode($gearComponent->getInstallations()),
            'maintenanceTasks' => Json::encode($gearComponent->getMaintenanceTasks()),
        ]);
        $this->eventBus->publishEvents($gearComponent->getRecordedEvents());
    }

    public function update(GearComponent $gearComponent): void
    {
        $sql = 'UPDATE GearComponent SET
                    label = :label,
                    localImagePath = :localImagePath,
                    purchasePriceAmount = :purchasePriceAmount,
                    purchasePriceCurrency = :purchasePriceCurrency,
                    isRetired = :isRetired,
                    notes = :notes,
                    installations = :installations,
                    maintenanceTasks = :maintenanceTasks
                WHERE gearComponentId = :gearComponentId';

        $this->connection->executeStatement($sql, [
            'gearComponentId' => (string) $gearComponent->getId(),
            'label' => (string) $gearComponent->getLabel(),
            'localImagePath' => $gearComponent->getLocalImagePath(),
            'purchasePriceAmount' => $gearComponent->getPurchasePrice()?->getAmount(),
            'purchasePriceCurrency' => $gearComponent->getPurchasePrice()?->getCurrency()->getCode(),
            'isRetired' => (int) $gearComponent->isRetired(),
            'notes' => $gearComponent->getNotes(),
            'installations' => Json::encode($gearComponent->getInstallations()),
            'maintenanceTasks' => Json::encode($gearComponent->getMaintenanceTasks()),
        ]);
        $this->eventBus->publishEvents($gearComponent->getRecordedEvents());
    }

    public function delete(GearComponent $gearComponent): void
    {
        $this->connection->executeStatement(
            'DELETE FROM GearComponent WHERE gearComponentId = :gearComponentId',
            ['gearComponentId' => (string) $gearComponent->getId()],
        );
        $this->eventBus->publishEvents($gearComponent->getRecordedEvents());
    }

    public function find(GearComponentId $gearComponentId): GearComponent
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM GearComponent WHERE gearComponentId = :gearComponentId',
            ['gearComponentId' => (string) $gearComponentId],
        )->fetchAssociative();

        if (false === $result) {
            throw new EntityNotFound(sprintf('GearComponent "%s" not found', $gearComponentId));
        }

        return $this->hydrate($result);
    }

    public function findAll(): GearComponents
    {
        return GearComponents::fromArray(array_map(
            $this->hydrate(...),
            $this->connection->executeQuery('SELECT * FROM GearComponent ORDER BY label ASC')->fetchAllAssociative(),
        ));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): GearComponent
    {
        return GearComponent::fromArray([
            'id' => $result['gearComponentId'],
            'label' => $result['label'],
            'localImagePath' => $result['localImagePath'],
            'purchasePrice' => null === $result['purchasePriceAmount'] ? null : [
                'amountInCents' => (int) $result['purchasePriceAmount'],
                'currency' => $result['purchasePriceCurrency'],
            ],
            'isRetired' => (bool) $result['isRetired'],
            'notes' => $result['notes'],
            'installations' => Json::decode($result['installations']),
            'maintenance' => Json::decode($result['maintenanceTasks']),
        ]);
    }
}
