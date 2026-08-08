<?php

namespace App\Tests\Infrastructure\Cache;

use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\ValueObject\Time\Year;
use PHPUnit\Framework\TestCase;

class CacheTagTest extends TestCase
{
    public function testTheCrossCuttingTagsAreDistinct(): void
    {
        $crossCuttingTags = CacheTag::crossCutting();
        $this->assertEquals(array_unique($crossCuttingTags, SORT_REGULAR), $crossCuttingTags);
    }

    public function testItCanBeScopedToAYear(): void
    {
        $this->assertEquals(
            'activities.2025',
            CacheTag::ACTIVITIES->forYear(Year::fromInt(2025))->toTagString()
        );
    }

    public function testEverySettingsGroupMapsToADistinctCacheTag(): void
    {
        $cacheTags = array_map(
            CacheTag::forSettingsGroup(...),
            SettingsGroup::cases()
        );

        $this->assertCount(count(SettingsGroup::cases()), array_unique($cacheTags, SORT_REGULAR));
    }

    public function testTheCacheTagOfASettingsGroupIsNamedAfterIt(): void
    {
        foreach (SettingsGroup::cases() as $settingsGroup) {
            $this->assertEquals(
                'settings.'.$settingsGroup->value,
                CacheTag::forSettingsGroup($settingsGroup)->value
            );
        }
    }
}
