<?php

declare(strict_types=1);

namespace App\Domain\Ftp;

use App\Infrastructure\Measurement\Mass\Kilogram;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class Ftp
{
    private ?Kilogram $athleteWeightInKg = null;

    private function __construct(
        private readonly SerializableDateTime $setOn,
        private readonly FtpValue $ftp,
    ) {
    }

    public static function fromState(
        SerializableDateTime $setOn,
        FtpValue $ftp,
    ): self {
        return new self(
            setOn: $setOn,
            ftp: $ftp
        );
    }

    public function getSetOn(): SerializableDateTime
    {
        return $this->setOn;
    }

    public function getFtp(): FtpValue
    {
        return $this->ftp;
    }

    public function getRelativeFtp(): ?float
    {
        if (!$this->athleteWeightInKg instanceof Kilogram) {
            return null;
        }

        return round($this->getFtp()->getValue() / $this->athleteWeightInKg->toFloat(), 1);
    }

    public function withAthleteWeight(?Kilogram $athleteWeight): self
    {
        if (is_null($athleteWeight)) {
            return $this;
        }

        return clone ($this, [
            'athleteWeightInKg' => $athleteWeight,
        ]);
    }
}
