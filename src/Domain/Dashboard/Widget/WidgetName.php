<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

final readonly class WidgetName implements \Stringable
{
    private function __construct(
        private string $name,
    ) {
    }

    public static function fromWidgetInstance(Widget $widget): self
    {
        return new self(lcfirst(str_replace('Widget', '', new \ReflectionClass($widget)->getShortName())));
    }

    public static function fromConfigValue(string $name): self
    {
        // We renamed ActivityIntensityWidget to ActivityGridWidget but want to keep the old name for backwards compatibility.
        if ('activityIntensity' === $name) {
            $name = 'activityGrid';
        }
        // We renamed YearlyDistancesWidget to YearlyStatsWidget but want to keep the old name for backwards compatibility.
        if ('yearlyDistances' === $name) {
            $name = 'yearlyStats';
        }
        // We renamed MostRecentActivitiesWithMapWidget to MostRecentActivityCardsWidget but want to keep the old name for backwards compatibility.
        if ('mostRecentActivitiesWithMap' === $name) {
            $name = 'mostRecentActivityCards';
        }

        return new self($name);
    }

    public function wasRemoved(): bool
    {
        return in_array($this->name, ['bestEfforts', 'weeklyGoals']);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
