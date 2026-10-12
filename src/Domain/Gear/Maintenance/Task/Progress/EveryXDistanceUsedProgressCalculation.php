<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Infrastructure\Measurement\Length\Meter;
use Doctrine\DBAL\Connection;

final readonly class EveryXDistanceUsedProgressCalculation implements MaintenanceTaskProgressCalculation
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function supports(IntervalUnit $intervalUnit): bool
    {
        return in_array($intervalUnit, [
            IntervalUnit::EVERY_X_KILOMETERS_USED,
            IntervalUnit::EVERY_X_MILES_USED,
        ]);
    }

    public function calculate(ProgressCalculationContext $context): MaintenanceTaskProgress
    {
        [$usageWindowsSql, $usageWindowsParameters] = $context->getUsageWindows()->toSql();
        $query = sprintf('
                SELECT SUM(distance) AS distance
                FROM Activity
                WHERE %s
                AND startDateTime > :lastTaggedOn', $usageWindowsSql);

        $distanceSinceLastTagged = Meter::from($this->connection->fetchOne($query, [
            ...$usageWindowsParameters,
            'lastTaggedOn' => (string) $context->getLastTaggedOn(),
        ]) ?? 0)->toKilometer();

        if (IntervalUnit::EVERY_X_MILES_USED === $context->getIntervalUnit()) {
            $distanceSinceLastTagged = $distanceSinceLastTagged->toMiles();
        }

        $intervalValue = $context->getIntervalValue();
        $symbol = $distanceSinceLastTagged->getSymbol();

        return MaintenanceTaskProgress::from(
            elapsed: $distanceSinceLastTagged->toFloat(),
            interval: $intervalValue,
            elapsedDescription: sprintf('%s %s', $distanceSinceLastTagged->toInt(), $symbol),
            remainingDescription: sprintf('%s %s', (int) abs($intervalValue - $distanceSinceLastTagged->toFloat()), $symbol),
        );
    }
}
