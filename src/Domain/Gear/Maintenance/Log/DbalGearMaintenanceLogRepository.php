<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Log;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class DbalGearMaintenanceLogRepository extends DbalRepository implements GearMaintenanceLogRepository
{
    public function __construct(
        Connection $connection,
        private EventBus $eventBus,
    ) {
        parent::__construct($connection);
    }

    public function add(GearMaintenanceLog $gearMaintenanceLog): void
    {
        $sql = 'INSERT INTO GearMaintenanceLog (gearMaintenanceLogId, gearId, maintenanceTaskId, performedOn)
        VALUES (:gearMaintenanceLogId, :gearId, :maintenanceTaskId, :performedOn)';

        $this->connection->executeStatement($sql, [
            'gearMaintenanceLogId' => $gearMaintenanceLog->getId(),
            'gearId' => $gearMaintenanceLog->getGearId(),
            'maintenanceTaskId' => $gearMaintenanceLog->getMaintenanceTaskId(),
            'performedOn' => $gearMaintenanceLog->getPerformedOn(),
        ]);

        $this->eventBus->publishEvents($gearMaintenanceLog->getRecordedEvents());
    }

    public function update(GearMaintenanceLog $gearMaintenanceLog): void
    {
        $sql = 'UPDATE GearMaintenanceLog SET
                    gearId = :gearId,
                    maintenanceTaskId = :maintenanceTaskId,
                    performedOn = :performedOn
                    WHERE gearMaintenanceLogId = :gearMaintenanceLogId';

        $this->connection->executeStatement($sql, [
            'gearMaintenanceLogId' => $gearMaintenanceLog->getId(),
            'gearId' => $gearMaintenanceLog->getGearId(),
            'maintenanceTaskId' => $gearMaintenanceLog->getMaintenanceTaskId(),
            'performedOn' => $gearMaintenanceLog->getPerformedOn(),
        ]);

        $this->eventBus->publishEvents($gearMaintenanceLog->getRecordedEvents());
    }

    public function find(GearMaintenanceLogId $gearMaintenanceLogId): GearMaintenanceLog
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM GearMaintenanceLog WHERE gearMaintenanceLogId = :gearMaintenanceLogId',
            ['gearMaintenanceLogId' => $gearMaintenanceLogId]
        )->fetchAssociative();

        if (false === $result) {
            throw new EntityNotFound(sprintf('GearMaintenanceLog "%s" not found', $gearMaintenanceLogId));
        }

        return $this->hydrate($result);
    }

    public function delete(GearMaintenanceLogId $gearMaintenanceLogId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM GearMaintenanceLog WHERE gearMaintenanceLogId = :gearMaintenanceLogId',
            ['gearMaintenanceLogId' => $gearMaintenanceLogId]
        );
        $this->eventBus->publishEvents([new GearMaintenanceLogWasUpdated()]);
    }

    public function findAll(): GearMaintenanceLogs
    {
        $results = $this->connection->executeQuery(
            'SELECT * FROM GearMaintenanceLog ORDER BY performedOn DESC'
        )->fetchAllAssociative();

        return GearMaintenanceLogs::fromArray(array_map(
            $this->hydrate(...),
            $results
        ));
    }

    public function findMostRecentForMaintenanceTask(MaintenanceTaskId $maintenanceTaskId): ?GearMaintenanceLog
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM GearMaintenanceLog WHERE maintenanceTaskId = :maintenanceTaskId ORDER BY performedOn DESC LIMIT 1',
            ['maintenanceTaskId' => $maintenanceTaskId]
        )->fetchAssociative();

        if (false === $result) {
            return null;
        }

        return $this->hydrate($result);
    }

    public function findMostRecentPerMaintenanceTask(): array
    {
        $results = $this->connection->executeQuery(
            'SELECT maintenanceTaskId, MAX(performedOn) AS performedOn
            FROM GearMaintenanceLog
            GROUP BY maintenanceTaskId'
        )->fetchAllAssociative();

        $mostRecentPerMaintenanceTask = [];
        foreach ($results as $result) {
            $mostRecentPerMaintenanceTask[$result['maintenanceTaskId']] = SerializableDateTime::fromString($result['performedOn']);
        }

        return $mostRecentPerMaintenanceTask;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): GearMaintenanceLog
    {
        return GearMaintenanceLog::fromState(
            gearMaintenanceLogId: GearMaintenanceLogId::fromString($result['gearMaintenanceLogId']),
            gearId: GearId::fromString($result['gearId']),
            maintenanceTaskId: MaintenanceTaskId::fromString($result['maintenanceTaskId']),
            performedOn: SerializableDateTime::fromString($result['performedOn']),
        );
    }
}
