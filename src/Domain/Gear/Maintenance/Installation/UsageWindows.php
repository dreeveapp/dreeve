<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Installation;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<UsageWindow>
 */
final class UsageWindows extends Collection
{
    public function getItemClassName(): string
    {
        return UsageWindow::class;
    }

    /**
     * @return array{string, array<string, string>}
     */
    public function toSql(): array
    {
        if ($this->isEmpty()) {
            return ['0 = 1', []];
        }

        $conditions = [];
        $parameters = [];
        foreach ($this as $index => $usageWindow) {
            $windowConditions = [sprintf('gearId = :usageWindow%dGearId', $index)];
            $parameters[sprintf('usageWindow%dGearId', $index)] = (string) $usageWindow->getGearId();

            if ($from = $usageWindow->getFrom()) {
                $windowConditions[] = sprintf('startDateTime >= :usageWindow%dFrom', $index);
                $parameters[sprintf('usageWindow%dFrom', $index)] = (string) $from;
            }
            if ($until = $usageWindow->getUntil()) {
                $windowConditions[] = sprintf('startDateTime < :usageWindow%dUntil', $index);
                $parameters[sprintf('usageWindow%dUntil', $index)] = (string) $until;
            }

            $conditions[] = '('.implode(' AND ', $windowConditions).')';
        }

        return ['('.implode(' OR ', $conditions).')', $parameters];
    }
}
