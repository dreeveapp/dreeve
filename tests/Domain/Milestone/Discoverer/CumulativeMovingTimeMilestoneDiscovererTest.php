<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\CumulativeMovingTimeContext;
use App\Domain\Milestone\Discoverer\CumulativeMovingTimeMilestoneDiscoverer;
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

class CumulativeMovingTimeMilestoneDiscovererTest extends ContainerTestCase
{
    private CumulativeMovingTimeMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverFirstThreshold(): void
    {
        $this->insertActivity(1, '2024-01-01', 86400);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_MOVING_TIME,
                    context: new CumulativeMovingTimeContext(threshold: Hour::from(24.0)),
                )->withFunComparison(MovingTimeFunComparison::FULL_DAY),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_MOVING_TIME,
                    context: new CumulativeMovingTimeContext(threshold: Hour::from(24.0)),
                )->withSportType(SportType::RIDE)->withFunComparison(MovingTimeFunComparison::FULL_DAY),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverMultipleThresholds(): void
    {
        $this->insertActivity(1, '2024-01-01', 100000);
        $this->insertActivity(2, '2024-01-02', 80000);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_MOVING_TIME,
                    context: new CumulativeMovingTimeContext(threshold: Hour::from(24.0)),
                )->withFunComparison(MovingTimeFunComparison::FULL_DAY),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_MOVING_TIME,
                    context: new CumulativeMovingTimeContext(threshold: Hour::from(24.0)),
                )->withSportType(SportType::RIDE)->withFunComparison(MovingTimeFunComparison::FULL_DAY),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_MOVING_TIME,
                    context: new CumulativeMovingTimeContext(threshold: Hour::from(48.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Hour::from(24.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(MovingTimeFunComparison::TWO_FULL_DAYS),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-4'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_MOVING_TIME,
                    context: new CumulativeMovingTimeContext(threshold: Hour::from(48.0)),
                )->withSportType(SportType::RIDE)->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-2'),
                    threshold: Hour::from(24.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(MovingTimeFunComparison::TWO_FULL_DAYS),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverSkipsZeroMovingTime(): void
    {
        $this->insertActivity(1, '2024-01-01', 0);
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverWithMultipleSportTypes(): void
    {
        $this->insertActivity(1, '2024-01-01', 86400, SportType::RIDE);
        $this->insertActivity(2, '2024-01-02', 86400, SportType::RUN);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_MOVING_TIME,
                    context: new CumulativeMovingTimeContext(threshold: Hour::from(24.0)),
                )->withFunComparison(MovingTimeFunComparison::FULL_DAY),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_MOVING_TIME,
                    context: new CumulativeMovingTimeContext(threshold: Hour::from(24.0)),
                )->withSportType(SportType::RIDE)->withFunComparison(MovingTimeFunComparison::FULL_DAY),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_MOVING_TIME,
                    context: new CumulativeMovingTimeContext(threshold: Hour::from(48.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Hour::from(24.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(MovingTimeFunComparison::TWO_FULL_DAYS),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-4'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_MOVING_TIME,
                    context: new CumulativeMovingTimeContext(threshold: Hour::from(24.0)),
                )->withSportType(SportType::RUN)->withFunComparison(MovingTimeFunComparison::FULL_DAY),
            ],
            $milestones->toArray(),
        );
    }

    public function testFunComparisonIsNullForSmallThreshold(): void
    {
        $this->insertActivity(1, '2024-01-01', 86400);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertNotNull($milestones->toArray()[0]->getFunComparison());
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new CumulativeMovingTimeMilestoneDiscoverer($this->getConnection());
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
