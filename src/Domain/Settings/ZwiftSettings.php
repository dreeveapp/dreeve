<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Zwift\ZwiftLevel;
use App\Domain\Zwift\ZwiftRacingScore;

final readonly class ZwiftSettings
{
    private function __construct(
        private ?ZwiftLevel $zwiftLevel,
        private ?ZwiftRacingScore $zwiftRacingScore,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            zwiftLevel: ZwiftLevel::fromOptionalString((string) ($data['level'] ?? null)),
            zwiftRacingScore: ZwiftRacingScore::fromOptionalString((string) ($data['racingScore'] ?? null)),
        );
    }

    public function getZwiftLevel(): ?ZwiftLevel
    {
        return $this->zwiftLevel;
    }

    public function getZwiftRacingScore(): ?ZwiftRacingScore
    {
        return $this->zwiftRacingScore;
    }
}
