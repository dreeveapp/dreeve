<?php

namespace App\Domain\Athlete;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

trait ProvideDateRangeBasedFormula
{
    /**
     * @return array<int, array<int, mixed>>
     */
    abstract protected function getRanges(): array;

    public function addRange(SerializableDateTime $on, int $maxHeartRate): self
    {
        $key = $on->getTimestamp();
        $ranges = $this->getRanges();
        if (!empty($ranges[$key])) {
            throw new InvalidHeartRateFormula('HEART_RATE_FORMULA cannot contain the same date more than once');
        }

        $ranges[$key] = [$on, $maxHeartRate];

        return new self($ranges);
    }

    public function calculate(int $age, SerializableDateTime $on): int
    {
        $on = SerializableDateTime::fromString($on->format('Y-m-d'));
        $ranges = $this->getRanges();

        foreach ($ranges as $range) {
            [$date, $maxHeartRate] = $range;
            if ($on->isAfterOrOn($date)) {
                return $maxHeartRate;
            }
        }

        if ([] === $ranges) {
            throw new InvalidHeartRateFormula(sprintf('HEART_RATE_FORMULA: could not determine heart rate for given date "%s"', $on->format('Y-m-d')));
        }

        [, $maxHeartRate] = $ranges[array_key_last($ranges)];

        return $maxHeartRate;
    }
}
