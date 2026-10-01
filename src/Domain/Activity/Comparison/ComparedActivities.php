<?php

declare(strict_types=1);

namespace App\Domain\Activity\Comparison;

use App\Domain\Activity\EnrichedActivity;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ComparedActivity>
 */
final class ComparedActivities extends Collection
{
    public function getItemClassName(): string
    {
        return ComparedActivity::class;
    }

    /**
     * @param EnrichedActivity[] $enrichedActivities
     */
    public static function fromEnrichedActivities(array $enrichedActivities): self
    {
        $comparedActivities = array_map(ComparedActivity::fromEnrichedActivity(...), $enrichedActivities);
        usort(
            $comparedActivities,
            fn (ComparedActivity $a, ComparedActivity $b): int => $a->getStartDateTime() <=> $b->getStartDateTime()
        );

        return self::fromArray($comparedActivities);
    }

    public function hasValuesFor(ComparisonMetric $metric, UnitSystem $unitSystem): bool
    {
        foreach ($this as $comparedActivity) {
            if (!is_null($comparedActivity->getValueFor($metric, $unitSystem))) {
                return true;
            }
        }

        return false;
    }
}
