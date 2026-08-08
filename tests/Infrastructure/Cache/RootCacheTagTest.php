<?php

namespace App\Tests\Infrastructure\Cache;

use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\Cache\RootCacheTag;
use App\Infrastructure\ValueObject\Time\Year;
use PHPUnit\Framework\TestCase;

class RootCacheTagTest extends TestCase
{
    public function testTheCrossCuttingTagsAreDistinct(): void
    {
        $crossCuttingTags = RootCacheTag::crossCutting();
        $this->assertEquals(array_unique($crossCuttingTags, SORT_REGULAR), $crossCuttingTags);
    }

    public function testItCanBeScopedToAYear(): void
    {
        $this->assertEquals(
            'activities.2025',
            RootCacheTag::ACTIVITIES->forYear(Year::fromInt(2025))->toTagString()
        );
    }

    public function testEverySettingsGroupMapsToADistinctCacheTag(): void
    {
        $cacheTags = array_map(
            RootCacheTag::forSettingsGroup(...),
            SettingsGroup::cases()
        );

        $this->assertCount(count(SettingsGroup::cases()), array_unique($cacheTags, SORT_REGULAR));
    }

    public function testTheCacheTagOfASettingsGroupIsNamedAfterIt(): void
    {
        foreach (SettingsGroup::cases() as $settingsGroup) {
            $this->assertEquals(
                'settings.'.$settingsGroup->value,
                RootCacheTag::forSettingsGroup($settingsGroup)->value
            );
        }
    }
}
