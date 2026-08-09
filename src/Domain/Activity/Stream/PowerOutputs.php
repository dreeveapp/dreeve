<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream;

use App\Infrastructure\Measurement\Mass\Kilogram;
use App\Infrastructure\ValueObject\Collection;
use Carbon\CarbonInterval;

/**
 * @extends Collection<PowerOutput>
 */
final class PowerOutputs extends Collection
{
    public function getItemClassName(): string
    {
        return PowerOutput::class;
    }

    /**
     * @param array<int|string, int> $bestAverages
     */
    public static function fromBestAverages(array $bestAverages, Kilogram $athleteWeight): self
    {
        $powerOutputs = self::empty();

        foreach (ActivityPowerRepository::TIME_INTERVALS_IN_SECONDS_REDACTED as $timeIntervalInSeconds) {
            if (!isset($bestAverages[$timeIntervalInSeconds])) {
                continue;
            }
            $bestAverageForTimeInterval = $bestAverages[$timeIntervalInSeconds];

            $interval = CarbonInterval::seconds($timeIntervalInSeconds);
            $powerOutputs->add(PowerOutput::fromState(
                timeIntervalInSeconds: $timeIntervalInSeconds,
                formattedTimeInterval: 0 !== (int) $interval->totalHours ? $interval->totalHours.' h' : (0 !== (int) $interval->totalMinutes ? $interval->totalMinutes.' m' : $interval->totalSeconds.' s'),
                power: $bestAverageForTimeInterval,
                relativePower: $athleteWeight->toFloat() > 0 ? round($bestAverageForTimeInterval / $athleteWeight->toFloat(), 2) : 0,
            ));
        }

        return $powerOutputs;
    }
}
