<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\ActivityRecordContext;
use App\Domain\Milestone\Discoverer\ActivityDistanceMilestoneDiscoverer;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Milestone\IncrementingMilestoneIdFactory;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityDistanceMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ActivityDistanceMilestoneDiscoverer $discoverer;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function testDiscoverCreatesPersonalBestForFirstActivity(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 50.0);

        $milestones = $this->discoverer->discover();

        $milestone = $milestones->getFirst();
        $context = $milestone->getContext();
        $this->assertInstanceOf(ActivityRecordContext::class, $context);
        $this->assertInstanceOf(Kilometer::class, $context->getValue());
        $this->assertEquals(50.0, $context->getValue()->toFloat());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverTracksImprovements(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 50.0);
        $this->insertActivity(2, '2024-01-02', SportType::RIDE, 80.0);

        $milestones = $this->discoverer->discover();

        $second = $milestones->toArray()[1];
        $context = $second->getContext();
        $this->assertInstanceOf(ActivityRecordContext::class, $context);
        $this->assertEquals(80.0, $context->getValue()->toFloat());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverDoesNotCreateMilestoneForNonImprovement(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 50.0);
        $this->insertActivity(2, '2024-01-02', SportType::RIDE, 30.0);

        $this->assertMatchesJsonSnapshot(Json::encode($this->discoverer->discover()));
    }

    public function testDiscoverSkipsZeroDistance(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 0.0);
        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->discoverer = new ActivityDistanceMilestoneDiscoverer($this->getConnection(), new IncrementingMilestoneIdFactory());
    }

    private function insertActivity(int $id, string $date, SportType $sportType, float $distanceKm): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withSportType($sportType)
                ->withDistance(Kilometer::from($distanceKm))
                ->build(), []
        ));
    }
}
