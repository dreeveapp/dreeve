<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EveryXHoursUsedProgressCalculation implements MaintenanceTaskProgressCalculation
{
    public function __construct(
        private Connection $connection,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(IntervalUnit $intervalUnit): bool
    {
        return IntervalUnit::EVERY_X_HOURS_USED === $intervalUnit;
    }

    public function calculate(ProgressCalculationContext $context): MaintenanceTaskProgress
    {
        $query = '
                SELECT SUM(movingTimeInSeconds) AS movingTimeInSeconds
                FROM Activity
                WHERE gearId IN(:gearIds)
                AND startDateTime > :lastTaggedOn';

        $movingTimeInSecondsSinceLastTagged = $this->connection->fetchOne($query, [
            'gearIds' => $context->getGearIds()->toArray(),
            'lastTaggedOn' => (string) $context->getLastTaggedOn(),
        ], [
            'gearIds' => ArrayParameterType::STRING,
        ]);
        $movingTimeInHoursSinceLastTagged = $movingTimeInSecondsSinceLastTagged / 3600;
        $intervalValue = $context->getIntervalValue();

        return MaintenanceTaskProgress::from(
            elapsed: $movingTimeInHoursSinceLastTagged,
            interval: $intervalValue,
            elapsedDescription: $this->describeHours($movingTimeInHoursSinceLastTagged),
            remainingDescription: $this->describeHours(abs($intervalValue - $movingTimeInHoursSinceLastTagged)),
        );
    }

    private function describeHours(float $hours): string
    {
        return $this->translator->trans('{hoursSinceLastTagged} hours', [
            '{hoursSinceLastTagged}' => round($hours),
        ]);
    }
}
