<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\BestEffort\ActivityBestEffort;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\PersonalBestContext;
use App\Domain\Milestone\Discoverer\PersonalBestMilestoneDiscoverer;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\PreviousMilestone;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class PersonalBestMilestoneDiscovererTest extends ContainerTestCase
{
    private PersonalBestMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverCreatesPersonalBestForFirstBestEffort(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RUN);
        $this->insertBestEffort(1, SportType::RUN, 5000, 1200);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::PERSONAL_BEST,
                    context: new PersonalBestContext(distance: Kilometer::from(5.0), time: Seconds::from(1200.0)),
                )->withSportType(SportType::RUN)->withActivityId(ActivityId::fromString('activity-1')),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverTracksImprovements(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RUN);
        $this->insertBestEffort(1, SportType::RUN, 5000, 1200);

        $this->insertActivity(2, '2024-01-02', SportType::RUN);
        $this->insertBestEffort(2, SportType::RUN, 5000, 1100);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::PERSONAL_BEST,
                    context: new PersonalBestContext(distance: Kilometer::from(5.0), time: Seconds::from(1200.0)),
                )->withSportType(SportType::RUN)->withActivityId(ActivityId::fromString('activity-1')),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::PERSONAL_BEST,
                    context: new PersonalBestContext(distance: Kilometer::from(5.0), time: Seconds::from(1100.0)),
                )->withSportType(SportType::RUN)->withActivityId(ActivityId::fromString('activity-2'))->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Seconds::from(1200.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                )),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverDoesNotCreateMilestoneForSlowerTime(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RUN);
        $this->insertBestEffort(1, SportType::RUN, 5000, 1200);

        $this->insertActivity(2, '2024-01-02', SportType::RUN);
        $this->insertBestEffort(2, SportType::RUN, 5000, 1500);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::PERSONAL_BEST,
                    context: new PersonalBestContext(distance: Kilometer::from(5.0), time: Seconds::from(1200.0)),
                )->withSportType(SportType::RUN)->withActivityId(ActivityId::fromString('activity-1')),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverTracksSportTypesSeparately(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RUN);
        $this->insertBestEffort(1, SportType::RUN, 5000, 1200);

        $this->insertActivity(2, '2024-01-02', SportType::RIDE);
        $this->insertBestEffort(2, SportType::RIDE, 10000, 1800);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::PERSONAL_BEST,
                    context: new PersonalBestContext(distance: Kilometer::from(5.0), time: Seconds::from(1200.0)),
                )->withSportType(SportType::RUN)->withActivityId(ActivityId::fromString('activity-1')),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::PERSONAL_BEST,
                    context: new PersonalBestContext(distance: Kilometer::from(10.0), time: Seconds::from(1800.0)),
                )->withSportType(SportType::RIDE)->withActivityId(ActivityId::fromString('activity-2')),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverTracksDistancesSeparately(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RUN);
        $this->insertBestEffort(1, SportType::RUN, 5000, 1200);
        $this->insertBestEffort(1, SportType::RUN, 10000, 2700);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::PERSONAL_BEST,
                    context: new PersonalBestContext(distance: Kilometer::from(5.0), time: Seconds::from(1200.0)),
                )->withSportType(SportType::RUN)->withActivityId(ActivityId::fromString('activity-1')),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::PERSONAL_BEST,
                    context: new PersonalBestContext(distance: Kilometer::from(10.0), time: Seconds::from(2700.0)),
                )->withSportType(SportType::RUN)->withActivityId(ActivityId::fromString('activity-1')),
            ],
            $milestones->toArray(),
        );
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new PersonalBestMilestoneDiscoverer($this->getConnection());
    }

    private function insertActivity(int $id, string $date, SportType $sportType): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withSportType($sportType)
                ->build(), []
        ));
    }

    private function insertBestEffort(int $activityId, SportType $sportType, int $distanceInMeter, int $timeInSeconds): void
    {
        $this->getContainer()->get(ActivityBestEffortRepository::class)->add(
            ActivityBestEffort::create(
                activityId: ActivityId::fromUnprefixed($activityId),
                distanceInMeter: Meter::from($distanceInMeter),
                sportType: $sportType,
                timeInSeconds: $timeInSeconds,
            )
        );
    }
}
