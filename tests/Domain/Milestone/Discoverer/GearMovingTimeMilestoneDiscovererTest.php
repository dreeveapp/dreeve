<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Milestone\Context\GearMovingTimeContext;
use App\Domain\Milestone\Discoverer\GearMovingTimeMilestoneDiscoverer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Milestone\IncrementingMilestoneIdFactory;
use Spatie\Snapshots\MatchesSnapshots;

class GearMovingTimeMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private GearMovingTimeMilestoneDiscoverer $discoverer;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function testDiscoverWithNoGear(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-01'))
                ->withMovingTimeInSeconds(100000)
                ->build(), []
        ));

        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function testDiscoverFirstThreshold(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($gearId)
                ->withName('Canyon Endurace')
                ->build()
        );
        $this->insertActivity('1', '2024-01-01', $gearId, 86400);

        $milestones = $this->discoverer->discover();

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(GearMovingTimeContext::class, $context);
        $this->assertEquals('Canyon Endurace', $context->getGearName());
        $this->assertEquals(24.0, $context->getThreshold()->toFloat());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverMultipleThresholdsWithPreviousChain(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($gearId)
                ->withName('Canyon Endurace')
                ->build()
        );
        $this->insertActivity('1', '2024-01-01', $gearId, 100000);
        $this->insertActivity('2', '2024-01-02', $gearId, 80000);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverTracksGearsSeparately(): void
    {
        $bikeId = GearId::fromUnprefixed('bike-1');
        $shoesId = GearId::fromUnprefixed('shoes-1');
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($bikeId)
                ->withName('Canyon Endurace')
                ->build()
        );
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($shoesId)
                ->withName('Nike Pegasus')
                ->build()
        );

        $this->insertActivity('1', '2024-01-01', $bikeId, 86400);
        $this->insertActivity('2', '2024-01-02', $shoesId, 86400);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverSkipsZeroMovingTime(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($gearId)
                ->withName('Canyon Endurace')
                ->build()
        );
        $this->insertActivity('1', '2024-01-01', $gearId, 0);

        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->discoverer = new GearMovingTimeMilestoneDiscoverer(
            $this->getConnection(),
            new IncrementingMilestoneIdFactory(),
        );
    }

    private function insertActivity(string $id, string $date, GearId $gearId, int $movingTimeInSeconds): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withMovingTimeInSeconds($movingTimeInSeconds)
                ->withGearId($gearId)
                ->build(), []
        ));
    }
}
