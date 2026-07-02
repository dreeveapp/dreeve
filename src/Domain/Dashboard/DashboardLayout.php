<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

final readonly class DashboardLayout implements \IteratorAggregate
{
    private function __construct(
        /** @var list<array{widget: string, width: int}> */
        private array $config,
    ) {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->config);
    }

    /**
     * @return list<array{widget: string, width: int}>
     */
    public static function default(): array
    {
        return [
            ['widget' => 'mostRecentActivities', 'width' => 66, 'config' => ['numberOfActivitiesToDisplay' => 5]],
            ['widget' => 'introText', 'width' => 33],
            ['widget' => 'weeklyStats', 'width' => 100, 'config' => ['metricsDisplayOrder' => ['distance', 'movingTime', 'elevation']]],
            ['widget' => 'activityGrid', 'width' => 100],
            ['widget' => 'streaks', 'width' => 33, 'config' => ['subtitle' => null, 'sportTypesToInclude' => []]],
            ['widget' => 'athleteProfile', 'width' => 33],
            ['widget' => 'eddington', 'width' => 33],
            ['widget' => 'peakPowerOutputs', 'width' => 50],
            ['widget' => 'heartRateZones', 'width' => 50],
            ['widget' => 'monthlyStats', 'width' => 66, 'config' => [
                'enableLastXYearsByDefault' => 10, 'metricsDisplayOrder' => ['distance', 'movingTime', 'elevation'],
            ]],
            ['widget' => 'mostRecentMilestones', 'width' => 33, 'config' => ['numberOfMilestonesToDisplay' => 5]],
            ['widget' => 'trainingLoad', 'width' => 100],
            ['widget' => 'weekdayStats', 'width' => 50],
            ['widget' => 'dayTimeStats', 'width' => 50],
            ['widget' => 'distanceBreakdown', 'width' => 50],
            ['widget' => 'gearStats', 'width' => 50, 'config' => ['includeRetiredGear' => true]],
            ['widget' => 'yearlyStats', 'width' => 100, 'config' => ['enableLastXYearsByDefault' => 10, 'metricsDisplayOrder' => ['distance', 'movingTime', 'elevation']]],
            ['widget' => 'zwiftStats', 'width' => 50],
            ['widget' => 'ftpHistory', 'width' => 50],
            ['widget' => 'challengeConsistency', 'width' => 50, 'config' => ['challenges' => []]],
            ['widget' => 'mostRecentChallengesCompleted', 'width' => 50, 'config' => ['numberOfChallengesToDisplay' => 5]],
            ['widget' => 'athleteWeightHistory', 'width' => 50],
        ];
    }

    /**
     * @param array<int, mixed> $config |null
     */
    public static function fromArray(
        ?array $config,
    ): self {
        if (null === $config || [] === $config) {
            $config = self::default();
        }

        foreach ($config as $widget) {
            foreach (['widget', 'width'] as $requiredKey) {
                if (array_key_exists($requiredKey, $widget)) {
                    continue;
                }
                throw new InvalidDashboardLayout(sprintf('"%s" property is required for each dashboard widget', $requiredKey));
            }

            if (!is_int($widget['width'])) {
                throw new InvalidDashboardLayout('"width" property must be a valid integer');
            }

            if (!in_array($widget['width'], [33, 50, 66, 100])) {
                throw new InvalidDashboardLayout(sprintf('"width" property must be one of [33, 50, 66, 100], found %s', $widget['width']));
            }

            if (array_key_exists('config', $widget)) {
                if (!is_array($widget['config'])) {
                    throw new InvalidDashboardLayout('"config" property must be an array');
                }
                foreach ($widget['config'] as $key => $value) {
                    if (is_null($value)) {
                        continue;
                    }
                    if (!is_int($value) && !is_string($value) && !is_float($value) && !is_bool($value) && !is_array($value)) {
                        throw new InvalidDashboardLayout(sprintf('Invalid type for config item "%s" in widget "%s". Expected int, string, float, bool or array.', $key, $widget['widget']));
                    }
                }
            }
        }

        return new self($config); // @phpstan-ignore argument.type
    }
}
