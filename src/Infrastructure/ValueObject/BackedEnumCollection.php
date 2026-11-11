<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject;

/**
 * @template T of \BackedEnum
 *
 * @extends Collection<T>
 */
abstract class BackedEnumCollection extends Collection
{
    /**
     * @param array<int|string> $values
     */
    public static function fromValues(array $values): static
    {
        /** @var class-string<T> $enumClass */
        $enumClass = static::empty()->getItemClassName();

        return static::fromArray(array_map($enumClass::from(...), $values));
    }

    /**
     * @param array<int|string> $values
     */
    public static function tryFromValues(array $values): ?static
    {
        /** @var class-string<T> $enumClass */
        $enumClass = static::empty()->getItemClassName();

        $enums = [];
        foreach ($values as $value) {
            if (null === $enum = $enumClass::tryFrom($value)) {
                return null;
            }
            $enums[] = $enum;
        }

        return static::fromArray($enums);
    }

    /**
     * @return list<value-of<T>>
     */
    public function toValues(): array
    {
        return array_column($this->toArray(), 'value');
    }
}
