<?php

namespace App\Tests\Infrastructure\Cache;

use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\Cache\CacheTag;
use PHPUnit\Framework\TestCase;

class CacheTagTest extends TestCase
{
    // A group silently sharing another group's tag would invalidate the wrong renders, so every group
    // must map to a tag of its own.
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
