<?php

declare(strict_types=1);

namespace App\Domain\Athlete\HeartRateZone;

final readonly class HeartRateZone
{
    public const string ONE = 'zone1';
    public const string TWO = 'zone2';
    public const string THREE = 'zone3';
    public const string FOUR = 'zone4';
    public const string FIVE = 'zone5';

    /**
     * @var array<string, string>
     */
    public const array COLORS = [
        self::ONE => '#DF584A',
        self::TWO => '#D63522',
        self::THREE => '#BD2D22',
        self::FOUR => '#942319',
        self::FIVE => '#6A1009',
    ];

    public function __construct(
        private string $name,
        private HeartRateZoneMode $mode,
        private int $from,
        private ?int $to,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getShortName(): string
    {
        return str_replace('zone', 'Z', $this->name);
    }

    public function getColor(): string
    {
        return self::COLORS[$this->name];
    }

    public function getFrom(): int
    {
        return $this->from;
    }

    public function getTo(): ?int
    {
        return $this->to;
    }

    public function getDifferenceBetweenFromAndToPercentage(int $athleteMaxHeartRate): int
    {
        return abs($this->getToPercentage($athleteMaxHeartRate) - $this->getFromPercentage($athleteMaxHeartRate));
    }

    public function getFromPercentage(int $athleteMaxHeartRate): int
    {
        if (HeartRateZoneMode::RELATIVE === $this->mode) {
            return $this->getFrom();
        }

        return (int) round($this->getFrom() / $athleteMaxHeartRate * 100);
    }

    public function getToPercentage(int $athleteMaxHeartRate): int
    {
        if (!$this->getTo()) {
            return 10000;
        }

        if (HeartRateZoneMode::RELATIVE === $this->mode) {
            return $this->getTo();
        }

        return (int) round($this->getTo() / $athleteMaxHeartRate * 100);
    }

    /**
     * @return array<int, int>
     */
    public function getRangeInBpm(int $athleteMaxHeartRate): array
    {
        if (HeartRateZoneMode::ABSOLUTE === $this->mode) {
            return [$this->getFrom(), $this->getTo() ?? 10000];
        }

        $percentageFrom = $this->getFrom() / 100;
        $percentageTo = $this->getTo() ? $this->getTo() / 100 : 100;

        return [(int) floor($athleteMaxHeartRate * $percentageFrom), (int) ceil($athleteMaxHeartRate * $percentageTo)];
    }
}
