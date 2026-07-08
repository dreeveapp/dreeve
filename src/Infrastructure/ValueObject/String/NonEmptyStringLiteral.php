<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\String;

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
        $words = array_map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1, self::ENCODING), self::ENCODING).mb_substr($word, 1, null, self::ENCODING), $this->words());

        $word = implode('', $words);

        return mb_strtolower(mb_substr($word, 0, 1, self::ENCODING), self::ENCODING).mb_substr($word, 1, null, self::ENCODING);
    }

    public function studlyCase(): string
    {
        $words = array_map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1, self::ENCODING), self::ENCODING).mb_substr($word, 1, null, self::ENCODING), $this->words());

        return implode('', $words);
    }

    public function pascalCase(): string
    {
        return $this->studlyCase();
    }

    public function snakeCase(): string
    {
        $words = array_map(fn (string $word): string => mb_strtolower($word, self::ENCODING), $this->words());

        return implode('_', $words);
    }

    public function kebabCase(): string
    {
        $words = array_map(fn (string $word): string => mb_strtolower($word, self::ENCODING), $this->words());

        return implode('-', $words);
    }

    /**
     * @return string[]
     */
    private function words(): array
    {
        preg_match_all('/[\p{L}0-9]+/u', $this->value, $matches);

        return array_filter($matches[0]);
    }
}
