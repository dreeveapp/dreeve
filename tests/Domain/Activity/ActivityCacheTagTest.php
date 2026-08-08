<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use PHPUnit\Framework\TestCase;

class ActivityCacheTagTest extends TestCase
{
    public function testItScopesTheActivitiesTagToOneActivity(): void
    {
        $this->assertEquals(
            'activities.12345',
            ActivityCacheTag::for(ActivityId::fromUnprefixed('12345'))->toTagString()
        );
    }

    public function testItIgnoresThePrefixSoBothUrlFormsHitTheSameTag(): void
    {
        $this->assertEquals(
            ActivityCacheTag::for(ActivityId::fromUnprefixed('12345'))->toTagString(),
            ActivityCacheTag::for(ActivityId::fromPrefixedOrUnprefixed('activity-12345'))->toTagString()
        );
    }
}
