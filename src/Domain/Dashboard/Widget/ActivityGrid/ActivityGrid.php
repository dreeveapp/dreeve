<?php

namespace App\Domain\Dashboard\Widget\ActivityGrid;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ActivityGrid
{
    /** @var array<int, array{0: string, 1: int}> */
    private array $data = [];

    private function __construct(
        /** @var array<int, array{min?: int|float, max?: int|float, value?: int, color: string, label: string}> */
        private readonly array $gridPieces,
        private readonly ActivityGridType $gridType,
    ) {
    }

    public static function create(
        ActivityGridType $activityGridType,
        TranslatorInterface $translator,
    ): self {
        return new self(
            gridPieces: $activityGridType->getPieces($translator),
            gridType: $activityGridType
        );
    }

    public function add(SerializableDateTime $on, int $value): void
    {
        $this->data[] = [$on->format('Y-m-d'), $value];
    }

    /**
     * @return array<int, array{min?: int|float, max?: int|float, value?: int, color: string, label: string}>
     */
    public function getPieces(): array
    {
        return $this->gridPieces;
    }

    public function getGridType(): ActivityGridType
    {
        return $this->gridType;
    }

    /**
     * @return array<int, array{0: string, 1: int}>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
