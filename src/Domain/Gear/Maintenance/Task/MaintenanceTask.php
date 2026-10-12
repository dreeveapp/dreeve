<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Task;

use App\Infrastructure\ValueObject\String\Name;

final readonly class MaintenanceTask implements \JsonSerializable
{
    private function __construct(
        private MaintenanceTaskId $id,
        private Name $label,
        private int $intervalValue,
        private IntervalUnit $intervalUnit,
    ) {
    }

    public static function create(
        MaintenanceTaskId $id,
        Name $label,
        int $intervalValue,
        IntervalUnit $intervalUnit,
    ): self {
        return new self(
            id: $id,
            label: $label,
            intervalValue: $intervalValue,
            intervalUnit: $intervalUnit,
        );
    }

    public function getId(): MaintenanceTaskId
    {
        return $this->id;
    }

    public function getLabel(): Name
    {
        return $this->label;
    }

    public function getIntervalValue(): int
    {
        return $this->intervalValue;
    }

    public function getIntervalUnit(): IntervalUnit
    {
        return $this->intervalUnit;
    }

    /**
     * @return array{id: string, label: string, interval: array{value: int, unit: string}}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => (string) $this->id,
            'label' => (string) $this->label,
            'interval' => [
                'value' => $this->intervalValue,
                'unit' => $this->intervalUnit->value,
            ],
        ];
    }
}
