<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser\Gpx;

/**
 * Some Android exporters (FitoTrack and friends) dump the Java toString() of their
 * workout entity into <metadata><name>.
 * https://github.com/dreeveapp/dreeve/issues/2404.
 */
final readonly class GpxWorkoutSummary
{
    private const string IDENTIFIER = 'workout';
    private const float MIN_MILLISECOND_TIMESTAMP = 1_000_000_000_000;
    private const float MAX_DISTANCE_IN_METER = 1_000_000;
    private const int MAX_ELAPSED_TIME_IN_SECONDS = 604_800;
    private const int MAX_CALORIES = 20_000;
    private const float JOULE_PER_KILOCALORIE = 4184;

    /** @var list<string> */
    private const array REQUIRED_FIELDS = ['start', 'end', 'distance', 'activeDuration'];

    /**
     * @param array<string, string> $fields
     */
    private function __construct(
        private array $fields,
    ) {
    }

    public static function tryFromString(string $value): ?self
    {
        if (1 !== preg_match('/^(?<identifier>[A-Za-z_][A-Za-z0-9_.$]*)\((?<body>.+)\)$/s', trim($value), $matches)) {
            return null;
        }
        if (self::IDENTIFIER !== strtolower($matches['identifier'])) {
            return null;
        }

        $fields = [];
        foreach (preg_split('/,\s*(?=[A-Za-z_][A-Za-z0-9_]*=)/', $matches['body']) ?: [] as $pair) {
            if (!str_contains($pair, '=')) {
                continue;
            }
            [$key, $fieldValue] = explode('=', $pair, 2);
            $key = trim($key);
            $fieldValue = trim($fieldValue);
            if ('' === $key || '' === $fieldValue) {
                continue;
            }
            $fields[$key] = $fieldValue;
        }

        if ([] === array_intersect_key($fields, array_flip(self::REQUIRED_FIELDS))) {
            return null;
        }

        return new self($fields);
    }

    public function getElapsedTimeInSeconds(): ?int
    {
        $start = $this->milliseconds('start');
        $end = $this->milliseconds('end');
        if (null === $start || null === $end) {
            return null;
        }

        $elapsed = (int) round(($end - $start) / 1000);
        if ($elapsed <= 0 || $elapsed > self::MAX_ELAPSED_TIME_IN_SECONDS) {
            return null;
        }

        return $elapsed;
    }

    public function getMovingTimeInSeconds(): ?int
    {
        if (null === ($activeDuration = $this->float('activeDuration'))) {
            return null;
        }

        $movingTime = (int) round($activeDuration / 1000);
        if ($movingTime <= 0 || $movingTime > self::MAX_ELAPSED_TIME_IN_SECONDS) {
            return null;
        }

        return $movingTime;
    }

    public function getDistanceInMeter(): ?float
    {
        $distance = $this->float('distance');
        if (null === $distance || $distance <= 0 || $distance >= self::MAX_DISTANCE_IN_METER) {
            return null;
        }

        return $distance;
    }

    public function getCalories(): ?int
    {
        if (null === ($energyInJoule = $this->float('energy'))) {
            return null;
        }

        $calories = (int) round($energyInJoule / self::JOULE_PER_KILOCALORIE);
        if ($calories <= 0 || $calories > self::MAX_CALORIES) {
            return null;
        }

        return $calories;
    }

    public function getDescription(): ?string
    {
        return $this->fields['comment'] ?? null;
    }

    private function milliseconds(string $key): ?float
    {
        $value = $this->float($key);
        if (null === $value || $value < self::MIN_MILLISECOND_TIMESTAMP) {
            return null;
        }

        return $value;
    }

    private function float(string $key): ?float
    {
        if (!isset($this->fields[$key]) || !is_numeric($this->fields[$key])) {
            return null;
        }

        return is_finite($value = (float) $this->fields[$key]) ? $value : null;
    }
}
