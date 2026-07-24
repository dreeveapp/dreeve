<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement;

trait ProvideMeasurementUnit
{
    private function __construct(
        private readonly float $value,
    ) {
    }

    public static function from(float $value): self
    {
        return new self($value);
    }

    public static function fromOptional(?float $value): self
    {
        if (null === $value) {
            return static::zero();
        }

        return new self($value);
    }

    public function isZeroOrLower(): bool
    {
        return $this->value <= 0;
    }

    public function isLowerThanOne(): bool
    {
        return $this->value < 1;
    }

    public static function zero(): self
    {
        return self::from(0);
    }

    public function toFloat(): float
    {
        return $this->value;
    }

    public function toInt(): int
    {
        return (int) $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->toFloat();
    }

    public function jsonSerialize(): float
    {
        return $this->toFloat();
    }

    public function subtract(Unit $value): self
    {
        if (self::class !== $value::class) {
            throw new \InvalidArgumentException(sprintf('Cannot subtract value of type "%s" with type "%s"', self::class, $value::class));
        }

        return self::from($this->toFloat() - $value->toFloat());
    }
}
