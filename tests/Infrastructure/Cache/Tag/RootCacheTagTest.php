<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cache\Tag;

use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use PHPUnit\Framework\TestCase;

class RootCacheTagTest extends TestCase
{
    public function testTheCrossCuttingTagsAreDistinct(): void
    {
        $crossCuttingTags = RootCacheTag::crossCutting();
        $this->assertEquals(array_unique($crossCuttingTags, SORT_REGULAR), $crossCuttingTags);
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
