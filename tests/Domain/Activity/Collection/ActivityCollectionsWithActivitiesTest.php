<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection;

use App\Domain\Activity\Activities;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollections;
use App\Domain\Activity\Collection\ActivityCollectionsWithActivities;
use App\Domain\Activity\Collection\ActivityCollectionWithActivities;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class ActivityCollectionsWithActivitiesTest extends TestCase
{
    public function testCreate(): void
    {
        $activities = Activities::fromArray([
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-06-20 08:00:00'))
                ->build(),
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-04-12 08:00:00'))
                ->build(),
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-06-22 08:00:00'))
                ->build(),
        ]);
        $activityCollections = ActivityCollections::fromArray([
            ActivityCollectionBuilder::fromDefaults()
                ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('lake-garda'))
                ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1')]))
                ->build(),
            ActivityCollectionBuilder::fromDefaults()
                ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('mallorca'))
                ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('2')]))
                ->build(),
            ActivityCollectionBuilder::fromDefaults()
                ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('transalp'))
                ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('3')]))
                ->withCoverMediaItemId(MediaItemId::fromUnprefixed('cover'))
                ->build(),
            ActivityCollectionBuilder::fromDefaults()
                ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('without-activities'))
                ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('deleted')]))
                ->build(),
        ]);

        $activityCollectionsWithActivities = ActivityCollectionsWithActivities::create(
            activityCollections: $activityCollections,
            activities: $activities,
        );

        $this->assertSame(
            ['activityCollection-transalp', 'activityCollection-mallorca', 'activityCollection-lake-garda'],
            $activityCollectionsWithActivities->map(
                static fn (ActivityCollectionWithActivities $activityCollectionWithActivities): string => (string) $activityCollectionWithActivities->getActivityCollection()->getId(),
            ),
        );
        $this->assertEquals(MediaItemId::fromUnprefixed('cover'), $activityCollectionsWithActivities->getFirst()?->getCoverMediaItemId());
        $this->assertEquals(
            MediaItemIds::fromArray([MediaItemId::fromUnprefixed('cover')]),
            $activityCollectionsWithActivities->getCoverMediaItemIds(),
        );
    }
}
