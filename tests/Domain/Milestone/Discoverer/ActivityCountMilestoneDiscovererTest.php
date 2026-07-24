<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Discoverer\ActivityCountMilestoneDiscoverer;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Milestone\IncrementingMilestoneIdFactory;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityCountMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ActivityCountMilestoneDiscoverer $discoverer;

    public function testDiscoverWithNoActivities(): void
    {
        $milestones = $this->discoverer->discover();
        $this->assertTrue($milestones->isEmpty());
    }

    public function testDiscoverFirstThreshold(): void
    {
        $this->insertActivities(10);

        $this->assertMatchesJsonSnapshot(Json::encode($this->discoverer->discover()));
    }

    public function testDiscoverMultipleThresholds(): void
    {
        $this->insertActivities(50);

        $milestones = $this->discoverer->discover();

        $this->assertCount(6, $milestones);

        $global50 = $milestones->toArray()[4];
        $this->assertNull($global50->getSportType());
        $this->assertNotNull($global50->getPrevious());
        $this->assertEquals('25', $global50->getPrevious()->getThreshold());

        $sport50 = $milestones->toArray()[5];
        $this->assertEquals(SportType::RIDE, $sport50->getSportType());
        $this->assertNotNull($sport50->getPrevious());
        $this->assertEquals('25', $sport50->getPrevious()->getThreshold());
    }

    public function testDiscoverWithMultipleSportTypes(): void
    {
        for ($i = 1; $i <= 15; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i)))
                    ->withSportType(SportType::RIDE)
                    ->withDistance(Kilometer::from(10))
                    ->build(), []
            ));
        }
        for ($i = 16; $i <= 25; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', $i)))
                    ->withSportType(SportType::RUN)
                    ->withDistance(Kilometer::from(10))
                    ->build(), []
            ));
        }

        $this->assertMatchesJsonSnapshot(Json::encode($this->discoverer->discover()));
    }

    public function testFunComparisonIsSet(): void
    {
        $this->insertActivities(50);
        $this->assertMatchesJsonSnapshot(Json::encode($this->discoverer->discover()));
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->discoverer = new ActivityCountMilestoneDiscoverer($this->getConnection(), new IncrementingMilestoneIdFactory());
    }

    private function insertActivities(int $count): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2024-01-%02d', min($i, 28))))
                    ->withDistance(Kilometer::from(10))
                    ->build(), []
            ));
        }
    }
}
