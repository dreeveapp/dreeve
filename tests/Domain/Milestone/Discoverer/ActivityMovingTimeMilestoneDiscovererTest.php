<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\ActivityRecordContext;
use App\Domain\Milestone\Discoverer\ActivityMovingTimeMilestoneDiscoverer;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class ActivityMovingTimeMilestoneDiscovererTest extends ContainerTestCase
{
    private ActivityMovingTimeMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverCreatesPersonalBestForFirstActivity(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 7200);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_MOVING_TIME,
                    context: new ActivityRecordContext(value: Seconds::from(7200.0)),
                )->withSportType(SportType::RIDE)->withActivityId(ActivityId::fromString('activity-1')),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverTracksImprovements(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 7200);
        $this->insertActivity(2, '2024-01-02', SportType::RIDE, 10800);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_MOVING_TIME,
                    context: new ActivityRecordContext(value: Seconds::from(7200.0)),
                )->withSportType(SportType::RIDE)->withActivityId(ActivityId::fromString('activity-1')),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_MOVING_TIME,
                    context: new ActivityRecordContext(value: Seconds::from(10800.0)),
                )->withSportType(SportType::RIDE)->withActivityId(ActivityId::fromString('activity-2'))->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Seconds::from(7200.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                )),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverDoesNotCreateMilestoneForNonImprovement(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 7200);
        $this->insertActivity(2, '2024-01-02', SportType::RIDE, 3600);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_MOVING_TIME,
                    context: new ActivityRecordContext(value: Seconds::from(7200.0)),
                )->withSportType(SportType::RIDE)->withActivityId(ActivityId::fromString('activity-1')),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverTracksSportTypesSeparately(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 7200);
        $this->insertActivity(2, '2024-01-02', SportType::RUN, 3600);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_MOVING_TIME,
                    context: new ActivityRecordContext(value: Seconds::from(7200.0)),
                )->withSportType(SportType::RIDE)->withActivityId(ActivityId::fromString('activity-1')),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::ACTIVITY_MOVING_TIME,
                    context: new ActivityRecordContext(value: Seconds::from(3600.0)),
                )->withSportType(SportType::RUN)->withActivityId(ActivityId::fromString('activity-2')),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverSkipsZeroMovingTime(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RIDE, 0);

        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new ActivityMovingTimeMilestoneDiscoverer($this->getConnection());
    }

    private function insertActivity(int $id, string $date, SportType $sportType, int $movingTimeInSeconds): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withSportType($sportType)
                ->withMovingTimeInSeconds($movingTimeInSeconds)
                ->build(), []
        ));
    }
}
