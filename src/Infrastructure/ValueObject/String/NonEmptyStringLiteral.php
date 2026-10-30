<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\String;

use function Symfony\Component\String\u;

abstract readonly class NonEmptyStringLiteral implements \JsonSerializable, \Stringable
{
    private const string ENCODING = 'UTF-8';
    private string $value;

    final private function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    protected function validate(string $value): void
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException(static::class.' can not be empty');
        }
    }

    public static function fromString(string $string): static
    {
        return new static($string);
    }

    public static function fromOptionalString(?string $string = null): ?static
    {
        $string = trim($string ?? '');
        if (!$string) {
            return null;
        }

        return new static($string);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    public function camelCase(): string
    {
        return (string) u($this->value)->camel();
    }

    public function studlyCase(): string
    {
        return $this->pascalCase();
    }

    public function pascalCase(): string
    {
        return (string) u($this->value)->pascal();
    }

    public function snakeCase(): string
    {
        return (string) u($this->value)->snake();
    }

    public function kebabCase(): string
    {
        // Not Symfony's kebab(): it turns "Garmin Edge 530" into "garmin-edge530" and "iPhone" into "i-phone", which would change persisted ids.
        preg_match_all('/[\p{L}0-9]+/u', $this->value, $matches);

        return mb_strtolower(implode('-', $matches[0]), self::ENCODING);
    }
}
