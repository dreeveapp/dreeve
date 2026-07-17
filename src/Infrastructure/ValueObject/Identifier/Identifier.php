<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Identifier;

use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;

abstract readonly class Identifier extends NonEmptyStringLiteral implements \JsonSerializable
{
    abstract public static function getPrefix(): string;

    public function toUnprefixedString(): string
    {
        if (!str_starts_with((string) $this, static::getPrefix())) {
            return (string) $this;
        }

        return substr_replace((string) $this, '', 0, strlen(static::getPrefix()));
    }

    #[\Override]
    protected function validate(string $value): void
    {
        parent::validate($value);

        if ('' !== static::getPrefix() && !str_starts_with($value, static::getPrefix())) {
            throw new \InvalidArgumentException('Identifier does not start with prefix "'.static::getPrefix().'", got: '.$value);
        }
    }

    public static function fromUnprefixed(string $unprefixed): static
    {
        return static::fromString(static::getPrefix().$unprefixed);
    }

    public static function fromOptionalUnprefixed(?string $unprefixed): ?static
    {
        if (is_null($unprefixed)) {
            return null;
        }

        return static::fromUnprefixed($unprefixed);
    }

    public static function fromPrefixedOrUnprefixed(string $value): static
    {
        return str_starts_with($value, static::getPrefix())
            ? static::fromString($value)
            : static::fromUnprefixed($value);
    }

    public static function random(): static
    {
        return static::fromString(static::getPrefix().new RandomUuidFactory()->random());
    }
}
