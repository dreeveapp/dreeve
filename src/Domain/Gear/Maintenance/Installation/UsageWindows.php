<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Installation;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Infrastructure\ValueObject\Collection;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

/**
 * @extends Collection<UsageWindow>
 */
final class UsageWindows extends Collection
{
    public function getItemClassName(): string
    {
        return UsageWindow::class;
    }

    public function getGearIds(): GearIds
    {
        return GearIds::fromArray($this->map(
            static fn (UsageWindow $usageWindow): GearId => $usageWindow->getGearId(),
        ))->unique();
    }

    public function getGearIdsActiveOn(SerializableDateTime $on): GearIds
    {
        return GearIds::fromArray($this
            ->filter(static fn (UsageWindow $usageWindow): bool => $usageWindow->isActiveOn($on))
            ->map(static fn (UsageWindow $usageWindow): GearId => $usageWindow->getGearId())
        )->unique();
    }

    public function getEarliestFrom(): ?SerializableDateTime
    {
        $earliestFrom = null;
        foreach ($this as $usageWindow) {
            if (!($from = $usageWindow->getFrom()) instanceof SerializableDateTime) {
                return null;
            }
            if (!$earliestFrom instanceof SerializableDateTime || $from->isBefore($earliestFrom)) {
                $earliestFrom = $from;
            }
        }

        return $earliestFrom;
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

            if (($from = $usageWindow->getFrom()) instanceof SerializableDateTime) {
                $windowConditions[] = sprintf('startDateTime >= :usageWindow%dFrom', $index);
                $parameters[sprintf('usageWindow%dFrom', $index)] = (string) $from;
            }
            if (($until = $usageWindow->getUntil()) instanceof SerializableDateTime) {
                $windowConditions[] = sprintf('startDateTime < :usageWindow%dUntil', $index);
                $parameters[sprintf('usageWindow%dUntil', $index)] = (string) $until;
            }
            if (!$usageWindow->getSportTypes()->isEmpty()) {
                $sportTypePlaceholders = [];
                foreach ($usageWindow->getSportTypes() as $sportTypeIndex => $sportType) {
                    $placeholder = sprintf('usageWindow%dSportType%d', $index, $sportTypeIndex);
                    $sportTypePlaceholders[] = ':'.$placeholder;
                    $parameters[$placeholder] = $sportType->value;
                }
                $windowConditions[] = sprintf('sportType IN (%s)', implode(', ', $sportTypePlaceholders));
            }

            $conditions[] = '('.implode(' AND ', $windowConditions).')';
        }

        return ['('.implode(' OR ', $conditions).')', $parameters];
    }
}
