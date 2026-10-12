<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Gear\Maintenance\Task\MaintenanceTasks;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

trait ParsesGearMaintenanceComponentPayload
{
    /**
     * @param array<string, mixed> $payload
     */
    private static function parseLabel(array $payload): Name
    {
        if (!isset($payload['label']) || !is_string($payload['label']) || '' === trim($payload['label'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A non-empty "label" is required.');
        }

        return Name::fromString(trim($payload['label']));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseInstallations(array $payload): GearComponentInstallations
    {
        if (!isset($payload['installations'])) {
            return GearComponentInstallations::empty();
        }
        if (!is_array($payload['installations'])) {
            throw CouldNotDeserializeCommand::invalidPayload('"installations" must be an array.');
        }

        $installations = GearComponentInstallations::empty();
        foreach ($payload['installations'] as $installation) {
            if (!is_array($installation)) {
                throw CouldNotDeserializeCommand::invalidPayload('Each installation must be an object.');
            }
            if (!isset($installation['gearId']) || !is_string($installation['gearId']) || '' === trim($installation['gearId'])) {
                throw CouldNotDeserializeCommand::invalidPayload('A non-empty "gearId" is required for each installation.');
            }

            $from = self::parseInstallationDate($installation, 'from');
            $until = self::parseInstallationDate($installation, 'until');
            if ($from && $until && $until->isBeforeOrOn($from)) {
                throw CouldNotDeserializeCommand::invalidPayload('The "until" date of an installation must be after its "from" date.');
            }

            $installations->add(GearComponentInstallation::create(
                gearId: GearId::fromUnprefixed(trim($installation['gearId'])),
                from: $from,
                until: $until,
            ));
        }

        return $installations;
    }

    /**
     * @param array<string, mixed> $installation
     */
    private static function parseInstallationDate(array $installation, string $key): ?SerializableDateTime
    {
        $date = $installation[$key] ?? null;
        if (null === $date || '' === $date) {
            return null;
        }
        if (!is_string($date) || !($parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date)) || $parsed->format('Y-m-d') !== $date) {
            throw CouldNotDeserializeCommand::invalidPayload(sprintf('Each installation "%s" date must be formatted as YYYY-MM-DD.', $key));
        }

        return SerializableDateTime::fromDateTimeImmutable($parsed);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseMaintenanceTasks(array $payload, bool $generateMissingIds): MaintenanceTasks
    {
        if (!isset($payload['maintenanceTasks']) || !is_array($payload['maintenanceTasks']) || [] === $payload['maintenanceTasks']) {
            throw CouldNotDeserializeCommand::invalidPayload('At least one maintenance task is required.');
        }

        $maintenanceTasks = MaintenanceTasks::empty();
        foreach ($payload['maintenanceTasks'] as $task) {
            if (!is_array($task)) {
                throw CouldNotDeserializeCommand::invalidPayload('Each maintenance task must be an object.');
            }
            if (!isset($task['label']) || !is_string($task['label']) || '' === trim($task['label'])) {
                throw CouldNotDeserializeCommand::invalidPayload('A non-empty "label" is required for each maintenance task.');
            }
            if (!isset($task['interval']['value'], $task['interval']['unit']) || !is_numeric($task['interval']['value'])) {
                throw CouldNotDeserializeCommand::invalidPayload('Each maintenance task requires an "interval" with "value" and "unit".');
            }
            if (!$intervalUnit = IntervalUnit::tryFrom((string) $task['interval']['unit'])) {
                throw CouldNotDeserializeCommand::invalidPayload(sprintf('Invalid interval unit "%s".', $task['interval']['unit']));
            }

            $taskId = null;
            if (isset($task['id']) && is_string($task['id']) && '' !== trim($task['id'])) {
                $taskId = MaintenanceTaskId::fromString(trim($task['id']));
            } elseif (!$generateMissingIds) {
                throw CouldNotDeserializeCommand::invalidPayload('An "id" is required for each maintenance task.');
            }

            $maintenanceTasks->add(MaintenanceTask::create(
                id: $taskId ?? MaintenanceTaskId::random(),
                label: Name::fromString(trim($task['label'])),
                intervalValue: (int) $task['interval']['value'],
                intervalUnit: $intervalUnit,
            ));
        }

        $ids = $maintenanceTasks->map(static fn (MaintenanceTask $task): string => (string) $task->getId());
        if (count($ids) !== count(array_unique($ids))) {
            throw CouldNotDeserializeCommand::invalidPayload('Duplicate maintenance task ids found.');
        }

        return $maintenanceTasks;
    }
}
