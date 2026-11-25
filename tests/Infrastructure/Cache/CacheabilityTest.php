<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cache;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use PHPUnit\Framework\TestCase;

class CacheabilityTest extends TestCase
{
    public function testItDoesNotDuplicateACrossCuttingTagThatWasAlsoDeclared(): void
    {
        $cacheTags = Cacheability::for('stub', CacheTags::of(RootCacheTag::SETTINGS_APPEARANCE))->getCacheTags()->toTagStrings();

        $this->assertEquals(array_unique($cacheTags), $cacheTags);
    }
}
