<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\ActivityRecordContext;
use App\Domain\Milestone\Discoverer\ActivityElevationMilestoneDiscoverer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Milestone\IncrementingMilestoneIdFactory;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityElevationMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ActivityElevationMilestoneDiscoverer $discoverer;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function testDiscoverCreatesPersonalBestForFirstActivity(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 300.0);

        $milestones = $this->discoverer->discover();

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(ActivityRecordContext::class, $context);
        $this->assertInstanceOf(Meter::class, $context->getValue());
        $this->assertEquals(300.0, $context->getValue()->toFloat());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverTracksImprovements(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 300.0);
        $this->insertActivity(2, '2024-01-02', SportType::RIDE, 500.0);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverDoesNotCreateMilestoneForNonImprovement(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 500.0);
        $this->insertActivity(2, '2024-01-02', SportType::RIDE, 200.0);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverTracksSportTypesSeparately(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 300.0);
        $this->insertActivity(2, '2024-01-02', SportType::RUN, 50.0);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverSkipsZeroElevation(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 0.0);
        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->discoverer = new ActivityElevationMilestoneDiscoverer($this->getConnection(), new IncrementingMilestoneIdFactory());
    }

    private function insertActivity(int $id, string $date, SportType $sportType, float $elevationM): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withSportType($sportType)
                ->withElevation(Meter::from($elevationM))
                ->build(), []
        ));
    }
}
