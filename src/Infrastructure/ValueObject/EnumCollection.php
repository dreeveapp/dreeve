<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject;

/**
 * @template T of \BackedEnum
 *
 * @extends Collection<T>
 */
abstract class EnumCollection extends Collection
{
    /**
     * @return list<value-of<T>>
     */
    public function toValues(): array
    {
        return array_column($this->toArray(), 'value');
    }
}
