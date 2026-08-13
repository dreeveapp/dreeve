<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EveryXDaysUsedProgressCalculation implements MaintenanceTaskProgressCalculation
{
    public function __construct(
        private Connection $connection,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(IntervalUnit $intervalUnit): bool
    {
        return IntervalUnit::EVERY_X_DAYS_USED === $intervalUnit;
    }

    public function calculate(ProgressCalculationContext $context): MaintenanceTaskProgress
    {
        $query = '
                SELECT strftime("%Y-%m-%d", startDateTime) AS day
                FROM Activity
                WHERE gearId IN(:gearIds)
                AND startDateTime > :lastTaggedOn
                GROUP BY day';

        $daysUsedSinceLastTagged = count($this->connection->fetchFirstColumn($query, [
            'gearIds' => $context->getGearIds()->toArray(),
            'lastTaggedOn' => (string) $context->getLastTaggedOn(),
        ], [
            'gearIds' => ArrayParameterType::STRING,
        ]));

        $intervalValue = $context->getIntervalValue();

        return MaintenanceTaskProgress::from(
            elapsed: $daysUsedSinceLastTagged,
            interval: $intervalValue,
            elapsedDescription: $this->describeDays($daysUsedSinceLastTagged),
            remainingDescription: $this->describeDays(abs($intervalValue - $daysUsedSinceLastTagged)),
        );
    }

    private function describeDays(float $days): string
    {
        return $this->translator->trans('{daysSinceLastTagged} days', [
            '{daysSinceLastTagged}' => round($days),
        ]);
    }
}
