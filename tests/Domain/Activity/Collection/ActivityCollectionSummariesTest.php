<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection;

use App\Domain\Activity\Activities;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollections;
use App\Domain\Activity\Collection\ActivityCollectionSummaries;
use App\Domain\Activity\Collection\ActivityCollectionSummary;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class ActivityCollectionSummariesTest extends TestCase
{
    public function testGroupByYear(): void
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
                ->build(),
            ActivityCollectionBuilder::fromDefaults()
                ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('without-activities'))
                ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('deleted')]))
                ->build(),
        ]);

        $summariesPerYear = ActivityCollectionSummaries::create(
            activityCollections: $activityCollections,
            activities: $activities,
        )->groupByYear();

        $this->assertSame(
            [
                2025 => ['activityCollection-transalp', 'activityCollection-mallorca'],
                2024 => ['activityCollection-lake-garda'],
            ],
            array_map(
                static fn (array $summaries): array => array_map(
                    static fn (ActivityCollectionSummary $summary): string => (string) $summary->getActivityCollection()->getId(),
                    $summaries,
                ),
                $summariesPerYear,
            ),
        );
    }
}
