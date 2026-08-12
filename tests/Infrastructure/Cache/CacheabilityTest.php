<?php

namespace App\Tests\Infrastructure\Cache;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use PHPUnit\Framework\TestCase;

class CacheabilityTest extends TestCase
{
    public function testEveryCacheabilityCarriesTheCrossCuttingTags(): void
    {
        $cacheTags = Cacheability::for('stub', CacheTags::empty())->getCacheTags()->toTagStrings();

        foreach (RootCacheTag::crossCutting() as $crossCuttingTag) {
            $this->assertContains($crossCuttingTag->value, $cacheTags);
        }
    }

    public function testItKeepsTheDeclaredTags(): void
    {
        $cacheability = Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::CHALLENGES));

        $this->assertContains(RootCacheTag::ACTIVITIES->value, $cacheability->getCacheTags()->toTagStrings());
        $this->assertContains(RootCacheTag::CHALLENGES->value, $cacheability->getCacheTags()->toTagStrings());
    }

    public function testItDoesNotDuplicateACrossCuttingTagThatWasAlsoDeclared(): void
    {
        $cacheTags = Cacheability::for('stub', CacheTags::of(RootCacheTag::SETTINGS_APPEARANCE))->getCacheTags()->toTagStrings();

        $this->assertEquals(array_unique($cacheTags), $cacheTags);
    }
}
