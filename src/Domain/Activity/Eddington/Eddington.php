<?php

declare(strict_types=1);

namespace App\Domain\Activity\Eddington;

use App\Domain\Activity\Eddington\Config\EddingtonConfigItem;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class Eddington
{
    /**
     * @param array<int<1, max>, int<0, max>>  $timesCompletedData
     * @param array<int, SerializableDateTime> $history
     * @param array<int, int>                  $daysToCompleteForFutureNumbers
     */
    private function __construct(
        private string $id,
        private string $label,
        private UnitSystem $unitSystem,
        private EddingtonConfigItem $config,
        private int $eddingtonNumber,
        private int $longestDistanceInADay,
        private array $timesCompletedData,
        private array $history,
        private array $daysToCompleteForFutureNumbers,
    ) {
    }

    /**
     * @param array<int<1, max>, int<0, max>>  $timesCompletedData
     * @param array<int, SerializableDateTime> $history
     * @param array<int, int>                  $daysToCompleteForFutureNumbers
     */
    public static function create(
        EddingtonConfigItem $config,
        UnitSystem $unitSystem,
        int $eddingtonNumber,
        int $longestDistanceInADay,
        array $timesCompletedData,
        array $history,
        array $daysToCompleteForFutureNumbers,
    ): self {
        return new self(
            id: $config->getId().$unitSystem->value,
            label: $config->getLabel(),
            unitSystem: $unitSystem,
            config: $config,
            eddingtonNumber: $eddingtonNumber,
            longestDistanceInADay: $longestDistanceInADay,
            timesCompletedData: $timesCompletedData,
            history: $history,
            daysToCompleteForFutureNumbers: $daysToCompleteForFutureNumbers,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getConfig(): EddingtonConfigItem
    {
        return $this->config;
    }

    public function getUnitSystem(): UnitSystem
    {
        return $this->unitSystem;
    }

    public function getNumber(): int
    {
        return $this->eddingtonNumber;
    }

    public function getLongestDistanceInADay(): int
    {
        return $this->longestDistanceInADay;
    }

    /**
     * @return array<int<1, max>, int<0, max>>
     */
    public function getTimesCompletedData(): array
    {
        return $this->timesCompletedData;
    }

    /**
     * @return array<int, int>
     */
    public function getDaysToCompleteForFutureNumbers(): array
    {
        return $this->daysToCompleteForFutureNumbers;
    }

    /**
     * @return array<int, SerializableDateTime>
     */
    public function getEddingtonHistory(): array
    {
        return $this->history;
    }
}
