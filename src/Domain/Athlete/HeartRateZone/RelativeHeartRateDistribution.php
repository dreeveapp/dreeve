<?php

declare(strict_types=1);

namespace App\Domain\Athlete\HeartRateZone;

final readonly class RelativeHeartRateDistribution
{
    private const float LOWER_PERCENTILE = 0.005;
    private const float UPPER_PERCENTILE = 0.999;
    private const int SNAP_TO = 5;

    /**
     * @param array<int, float> $secondsPerPercentage
     */
    private function __construct(
        private array $secondsPerPercentage,
    ) {
    }

    /**
     * @param array<int, float> $secondsPerPercentage
     */
    public static function fromSecondsPerPercentage(array $secondsPerPercentage): self
    {
        ksort($secondsPerPercentage);

        return new self($secondsPerPercentage);
    }

    public function getTotalTimeInSeconds(): int
    {
        return (int) round(array_sum($this->secondsPerPercentage));
    }

    /**
     * @return array<int, int>
     */
    public function getBins(): array
    {
        if ([] === $this->secondsPerPercentage) {
            return [];
        }

        $total = array_sum($this->secondsPerPercentage);
        $lowerBound = null;
        $upperBound = null;
        $cumulative = 0;
        foreach ($this->secondsPerPercentage as $percentage => $seconds) {
            $cumulative += $seconds;
            if (null === $lowerBound && $cumulative >= $total * self::LOWER_PERCENTILE) {
                $lowerBound = (int) floor($percentage / self::SNAP_TO) * self::SNAP_TO;
            }
            if (null === $upperBound && $cumulative >= $total * self::UPPER_PERCENTILE) {
                $upperBound = (int) ceil($percentage / self::SNAP_TO) * self::SNAP_TO;
            }
        }
        $lowerBound ??= (int) array_key_first($this->secondsPerPercentage);
        $upperBound ??= (int) array_key_last($this->secondsPerPercentage);

        $bins = array_fill($lowerBound, $upperBound - $lowerBound + 1, 0.0);
        foreach ($this->secondsPerPercentage as $percentage => $seconds) {
            $bins[min(max($percentage, $lowerBound), $upperBound)] += $seconds;
        }

        return array_map(fn (float $seconds): int => (int) round($seconds), $bins);
    }
}
