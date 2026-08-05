<?php

namespace App\Tests\Infrastructure\Cache;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use PHPUnit\Framework\TestCase;

class CacheabilityTest extends TestCase
{
    public function testEveryCacheabilityCarriesTheCrossCuttingTags(): void
    {
        $cacheTags = Cacheability::for('stub', CacheTags::empty())->getCacheTags()->toTagStrings();

        foreach (CacheTag::crossCutting() as $crossCuttingTag) {
            $this->assertContains($crossCuttingTag->value, $cacheTags);
        }
    }

    public function testItKeepsTheDeclaredTags(): void
    {
        $cacheability = Cacheability::for('stub', CacheTags::of(CacheTag::ACTIVITIES, CacheTag::CHALLENGES));

        $this->assertContains(CacheTag::ACTIVITIES->value, $cacheability->getCacheTags()->toTagStrings());
        $this->assertContains(CacheTag::CHALLENGES->value, $cacheability->getCacheTags()->toTagStrings());
    }

    public function testItDoesNotDuplicateACrossCuttingTagThatWasAlsoDeclared(): void
    {
        $cacheTags = Cacheability::for('stub', CacheTags::of(CacheTag::SETTINGS_APPEARANCE))->getCacheTags()->toTagStrings();

        $this->assertEquals(array_unique($cacheTags), $cacheTags);
    }

    public function testSomethingThatIsNotCacheableCarriesNoTags(): void
    {
        $cacheability = Cacheability::none();

        $this->assertFalse($cacheability->isCacheable());
        $this->assertEmpty($cacheability->getCacheTags()->toTagStrings());
    }
}
