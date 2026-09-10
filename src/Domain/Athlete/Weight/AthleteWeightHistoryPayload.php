<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class AthleteWeightHistoryPayload
{
    /**
     * @param list<mixed> $entries
     */
    private function __construct(
        private array $entries,
    ) {
    }

    public static function fromStoredValue(mixed $weightHistory): self
    {
        return new self(is_array($weightHistory) ? array_values($weightHistory) : []);
    }

    public function with(SerializableDateTime $on, float $weight): self
    {
        $entries = $this->without($on)->entries;
        $entries[] = ['on' => $on->format('Y-m-d'), 'weight' => $weight];

        return new self($entries);
    }

    public function without(SerializableDateTime $on): self
    {
        $formattedOn = $on->format('Y-m-d');

        return new self(array_values(array_filter(
            $this->entries,
            static fn (mixed $entry): bool => !is_array($entry) || $formattedOn !== ($entry['on'] ?? null),
        )));
    }

    /**
     * @return list<mixed>
     */
    public function toArray(): array
    {
        return $this->entries;
    }
}
