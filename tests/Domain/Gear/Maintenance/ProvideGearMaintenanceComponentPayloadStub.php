<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\ProvideGearMaintenanceComponentPayload;
use App\Domain\Gear\Maintenance\Task\MaintenanceTasks;
use App\Domain\Gear\ProvideNotesFromPayload;
use App\Infrastructure\ValueObject\String\Name;

final class ProvideGearMaintenanceComponentPayloadStub
{
    use ProvideGearMaintenanceComponentPayload;
    use ProvideNotesFromPayload;

    /**
     * @param array<string, mixed> $payload
     */
    public function doParseLabel(array $payload): Name
    {
        return self::parseLabel($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function doParseNotes(array $payload): ?string
    {
        return self::parseNotes($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function doParseInstallations(array $payload): GearComponentInstallations
    {
        return self::parseInstallations($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function doParseMaintenanceTasks(array $payload, bool $generateMissingIds): MaintenanceTasks
    {
        return self::parseMaintenanceTasks(
            payload: $payload,
            generateMissingIds: $generateMissingIds,
        );
    }
}
