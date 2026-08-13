<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Infrastructure\Time\Clock\Clock;
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

    public function calculate(ProgressCalculationContext $context): MaintenanceTaskProgress
    {
        $today = $this->clock->getCurrentDateTimeImmutable();
        $daysSinceLastTagged = (int) $today->diff($context->getLastTaggedOn())->days;
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
