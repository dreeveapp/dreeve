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
use App\Domain\Milestone\MilestoneIdFactory;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class PersonalBestMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

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

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(PersonalBestContext::class, $context);
        $this->assertInstanceOf(Kilometer::class, $context->getDistance());
        $this->assertEquals(5.0, $context->getDistance()->toFloat());
        $this->assertInstanceOf(Seconds::class, $context->getTime());
        $this->assertEquals(1200, $context->getTime()->toInt());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverTracksImprovements(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RUN);
        $this->insertBestEffort(1, SportType::RUN, 5000, 1200);

        $this->insertActivity(2, '2024-01-02', SportType::RUN);
        $this->insertBestEffort(2, SportType::RUN, 5000, 1100);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverDoesNotCreateMilestoneForSlowerTime(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RUN);
        $this->insertBestEffort(1, SportType::RUN, 5000, 1200);

        $this->insertActivity(2, '2024-01-02', SportType::RUN);
        $this->insertBestEffort(2, SportType::RUN, 5000, 1500);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverTracksSportTypesSeparately(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RUN);
        $this->insertBestEffort(1, SportType::RUN, 5000, 1200);

        $this->insertActivity(2, '2024-01-02', SportType::RIDE);
        $this->insertBestEffort(2, SportType::RIDE, 10000, 1800);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverTracksDistancesSeparately(): void
    {
        $this->insertActivity(1, '2024-01-01', SportType::RUN);
        $this->insertBestEffort(1, SportType::RUN, 5000, 1200);
        $this->insertBestEffort(1, SportType::RUN, 10000, 2700);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
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
