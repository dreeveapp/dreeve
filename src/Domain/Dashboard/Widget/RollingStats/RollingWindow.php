<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\RollingStats;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class RollingWindow
{
    private function __construct(
        private SerializableDateTime $from,
        private SerializableDateTime $to,
    ) {
    }

    public static function endingOn(SerializableDateTime $day, int $windowInDays): self
    {
        $to = SerializableDateTime::fromString($day->format('Y-m-d'));

        return new self(
            from: SerializableDateTime::fromString($to->modify(sprintf('-%d days', $windowInDays - 1))->format('Y-m-d')),
            to: $to,
        );
    }

    public function getLabel(): string
    {
        return $this->to->translatedFormat('M d');
    }

    public function getFrom(): SerializableDateTime
    {
        return $this->from;
    }

    public function getTo(): SerializableDateTime
    {
        return $this->to;
    }
}
