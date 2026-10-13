<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
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
        [$usageWindowsSql, $parameters] = $context->getUsageWindows()->toSql();
        $query = sprintf('
                SELECT strftime("%%Y-%%m-%%d", startDateTime) AS day
                FROM Activity
                WHERE %s', $usageWindowsSql);
        if (($lastTaggedOn = $context->getLastTaggedOn()) instanceof SerializableDateTime) {
            $query .= ' AND startDateTime > :lastTaggedOn';
            $parameters['lastTaggedOn'] = (string) $lastTaggedOn;
        }
        $query .= ' GROUP BY day';

        $daysUsedSinceLastTagged = count($this->connection->fetchFirstColumn($query, $parameters));

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
