<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement;

interface Unit extends \Stringable, \JsonSerializable
{
    public static function from(float $value): self;

    public static function zero(): self;

    public function isZeroOrLower(): bool;

    public function isLowerThanOne(): bool;

    public function getSymbol(): string;

    public function toFloat(): float;

    public function toInt(): int;

    public function subtract(Unit $value): self;
}
