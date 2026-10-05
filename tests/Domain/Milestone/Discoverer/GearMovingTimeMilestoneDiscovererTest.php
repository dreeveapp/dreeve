<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Milestone\Context\GearMovingTimeContext;
use App\Domain\Milestone\Discoverer\GearMovingTimeMilestoneDiscoverer;
use App\Domain\Milestone\FunComparison\MovingTimeFunComparison;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\Time\Hour;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;

class GearMovingTimeMilestoneDiscovererTest extends ContainerTestCase
{
    private GearMovingTimeMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
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

        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
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

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_MOVING_TIME,
                    context: new GearMovingTimeContext(gearName: 'Canyon Endurace', threshold: Hour::from(24.0)),
                )->withFunComparison(MovingTimeFunComparison::FULL_DAY),
            ],
            $milestones->toArray(),
        );
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

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_MOVING_TIME,
                    context: new GearMovingTimeContext(gearName: 'Canyon Endurace', threshold: Hour::from(24.0)),
                )->withFunComparison(MovingTimeFunComparison::FULL_DAY),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::GEAR_MOVING_TIME,
                    context: new GearMovingTimeContext(gearName: 'Canyon Endurace', threshold: Hour::from(48.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Hour::from(24.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(MovingTimeFunComparison::TWO_FULL_DAYS),
            ],
            $milestones->toArray(),
        );
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

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_MOVING_TIME,
                    context: new GearMovingTimeContext(gearName: 'Canyon Endurace', threshold: Hour::from(24.0)),
                )->withFunComparison(MovingTimeFunComparison::FULL_DAY),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::GEAR_MOVING_TIME,
                    context: new GearMovingTimeContext(gearName: 'Nike Pegasus', threshold: Hour::from(24.0)),
                )->withFunComparison(MovingTimeFunComparison::FULL_DAY),
            ],
            $milestones->toArray(),
        );
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

        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new GearMovingTimeMilestoneDiscoverer(
            $this->getConnection(),
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
