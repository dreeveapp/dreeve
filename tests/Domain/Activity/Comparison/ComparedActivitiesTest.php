<?php

namespace App\Tests\Domain\Activity\Comparison;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Comparison\ComparedActivities;
use App\Domain\Activity\Comparison\ComparedActivity;
use App\Domain\Activity\Comparison\ComparisonMetric;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\EnrichedActivityBuilder;
use PHPUnit\Framework\TestCase;

class ComparedActivitiesTest extends TestCase
{
    public function testFromEnrichedActivitiesSortsOldestFirst(): void
    {
        $comparedActivities = ComparedActivities::fromEnrichedActivities([
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('newest'))
                    ->withStartDateTime(SerializableDateTime::fromString('2024-03-01'))
                    ->build()
            )->build(),
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('oldest'))
                    ->withStartDateTime(SerializableDateTime::fromString('2023-01-01'))
                    ->build()
            )->build(),
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('middle'))
                    ->withStartDateTime(SerializableDateTime::fromString('2023-08-15'))
                    ->build()
            )->build(),
        ]);

        $this->assertEquals(
            ['activity-oldest', 'activity-middle', 'activity-newest'],
            $comparedActivities->map(fn (ComparedActivity $comparedActivity): string => (string) $comparedActivity->getActivityId())
        );
    }

    public function testHasValuesFor(): void
    {
        $comparedActivities = ComparedActivities::fromEnrichedActivities([
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('without-power'))
                    ->build()
            )->build(),
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('with-power'))
                    ->withAveragePower(200)
                    ->build()
            )->build(),
        ]);

        $this->assertTrue($comparedActivities->hasValuesFor(ComparisonMetric::AVERAGE_POWER, UnitSystem::METRIC));
        $this->assertFalse($comparedActivities->hasValuesFor(ComparisonMetric::EFFICIENCY_FACTOR, UnitSystem::METRIC));
    }

    public function testHasValuesForAnEmptySet(): void
    {
        $this->assertFalse(ComparedActivities::empty()->hasValuesFor(ComparisonMetric::MOVING_TIME, UnitSystem::METRIC));
    }
}
