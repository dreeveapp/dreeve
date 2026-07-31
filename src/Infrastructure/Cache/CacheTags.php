<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<CacheTag>
 */
final class CacheTags extends Collection
{
    public function getItemClassName(): string
    {
        return CacheTag::class;
    }

    public static function of(CacheTag ...$cacheTags): self
    {
        return self::fromArray($cacheTags);
    }

    /**
     * @return string[]
     */
    public function toTagStrings(): array
    {
        return array_values(array_unique($this->map(fn (CacheTag $cacheTag): string => $cacheTag->value)));
    }
}
