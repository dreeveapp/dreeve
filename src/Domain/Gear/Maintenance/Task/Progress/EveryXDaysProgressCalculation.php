<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EveryXDaysProgressCalculation implements MaintenanceTaskProgressCalculation
{
    public function __construct(
        private TranslatorInterface $translator,
        private Clock $clock,
    ) {
    }

    public function supports(IntervalUnit $intervalUnit): bool
    {
        return IntervalUnit::EVERY_X_DAYS === $intervalUnit;
    }

    public function calculate(ProgressCalculationContext $context): ?MaintenanceTaskProgress
    {
        $countFrom = $context->getLastTaggedOn() ?? $context->getUsageWindows()->getEarliestFrom();
        if (!$countFrom instanceof SerializableDateTime) {
            return null;
        }

        $today = $this->clock->getCurrentDateTimeImmutable();
        $daysSinceLastTagged = (int) $today->diff($countFrom)->days;
        $intervalValue = $context->getIntervalValue();

        return MaintenanceTaskProgress::from(
            elapsed: $daysSinceLastTagged,
            interval: $intervalValue,
            elapsedDescription: $this->describeDays($daysSinceLastTagged),
            remainingDescription: $this->describeDays(abs($intervalValue - $daysSinceLastTagged)),
        );
    }

    private function describeDays(float $days): string
    {
        return $this->translator->trans('{daysSinceLastTagged} days', [
            '{daysSinceLastTagged}' => round($days),
        ]);
    }
}
