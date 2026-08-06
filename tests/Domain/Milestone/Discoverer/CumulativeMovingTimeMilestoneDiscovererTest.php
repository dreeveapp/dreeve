<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\CumulativeMovingTimeContext;
use App\Domain\Milestone\Discoverer\CumulativeMovingTimeMilestoneDiscoverer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Milestone\IncrementingMilestoneIdFactory;
use Spatie\Snapshots\MatchesSnapshots;

class CumulativeMovingTimeMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private CumulativeMovingTimeMilestoneDiscoverer $discoverer;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function testDiscoverFirstThreshold(): void
    {
        $this->insertActivity(1, '2024-01-01', 86400);

        $milestones = $this->discoverer->discover();

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(CumulativeMovingTimeContext::class, $context);
        $this->assertEquals(24.0, $context->getThreshold()->toFloat());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverMultipleThresholds(): void
    {
        $this->insertActivity(1, '2024-01-01', 100000);
        $this->insertActivity(2, '2024-01-02', 80000);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverSkipsZeroMovingTime(): void
    {
        $this->insertActivity(1, '2024-01-01', 0);
        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function testDiscoverWithMultipleSportTypes(): void
    {
        $this->insertActivity(1, '2024-01-01', 86400, SportType::RIDE);
        $this->insertActivity(2, '2024-01-02', 86400, SportType::RUN);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testFunComparisonIsNullForSmallThreshold(): void
    {
        $this->insertActivity(1, '2024-01-01', 86400);

        $milestones = $this->discoverer->discover();
        $this->assertNotNull($milestones->toArray()[0]->getFunComparison());
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->discoverer = new CumulativeMovingTimeMilestoneDiscoverer($this->getConnection(), new IncrementingMilestoneIdFactory());
    }

    private function insertActivity(
        int $id,
        string $date,
        int $movingTimeInSeconds,
        SportType $sportType = SportType::RIDE,
    ): void {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withMovingTimeInSeconds($movingTimeInSeconds)
                ->withSportType($sportType)
                ->build(), []
        ));
    }
}
