<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Gear\Maintenance\Task\MaintenanceTasks;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\String\Name;

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
    private static function parseAttachedTo(array $payload): GearIds
    {
        if (!isset($payload['attachedTo']) || !is_array($payload['attachedTo'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "attachedTo" array is required.');
        }

        return GearIds::fromArray(array_map(
            static function (mixed $gearId): GearId {
                if (!is_string($gearId) || '' === trim($gearId)) {
                    throw CouldNotDeserializeCommand::invalidPayload('Each "attachedTo" entry must be a non-empty string.');
                }

                return GearId::fromUnprefixed(trim($gearId));
            },
            array_values($payload['attachedTo']),
        ));
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
