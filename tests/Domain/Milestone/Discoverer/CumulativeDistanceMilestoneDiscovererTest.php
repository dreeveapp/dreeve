<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\CumulativeDistanceContext;
use App\Domain\Milestone\Discoverer\CumulativeDistanceMilestoneDiscoverer;
use App\Domain\Milestone\FunComparison\DistanceFunComparison;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\PreviousMilestone;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class CumulativeDistanceMilestoneDiscovererTest extends ContainerTestCase
{
    private CumulativeDistanceMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverFirstMetricThreshold(): void
    {
        $this->insertActivity(1, '2024-01-01', 100.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(100.0)),
                )->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(100.0)),
                )->withSportType(SportType::RIDE)->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverMultipleThresholds(): void
    {
        $this->insertActivity(1, '2024-01-01', 250.0);
        $this->insertActivity(2, '2024-01-02', 260.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(100.0)),
                )->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(250.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Kilometer::from(100.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(DistanceFunComparison::LENGTH_OF_JAMAICA),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(100.0)),
                )->withSportType(SportType::RIDE)->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-4'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(250.0)),
                )->withSportType(SportType::RIDE)->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-3'),
                    threshold: Kilometer::from(100.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(DistanceFunComparison::LENGTH_OF_JAMAICA),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-5'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(500.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-2'),
                    threshold: Kilometer::from(250.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(DistanceFunComparison::MADRID_TO_BARCELONA),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-6'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(500.0)),
                )->withSportType(SportType::RIDE)->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-4'),
                    threshold: Kilometer::from(250.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(DistanceFunComparison::MADRID_TO_BARCELONA),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverSkipsZeroDistance(): void
    {
        $this->insertActivity(1, '2024-01-01', 0.0);
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverWithImperialUnits(): void
    {
        $this->insertActivity(1, '2024-01-01', 161.0);

        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsName::UNIT_SYSTEM, 'imperial');
        $discoverer = new CumulativeDistanceMilestoneDiscoverer(
            $this->getConnection(),
            $settingsRepository,
        );
        $milestones = $discoverer->discover($this->milestoneIdFactory);
        $this->assertGreaterThanOrEqual(2, count($milestones));
    }

    public function testDiscoverWithMultipleSportTypes(): void
    {
        $this->insertActivity(1, '2024-01-01', 150.0, SportType::RIDE);
        $this->insertActivity(2, '2024-01-02', 110.0, SportType::RUN);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(100.0)),
                )->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(100.0)),
                )->withSportType(SportType::RIDE)->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(250.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Kilometer::from(100.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(DistanceFunComparison::LENGTH_OF_JAMAICA),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-4'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_DISTANCE,
                    context: new CumulativeDistanceContext(threshold: Kilometer::from(100.0)),
                )->withSportType(SportType::RUN)->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
            ],
            $milestones->toArray(),
        );
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new CumulativeDistanceMilestoneDiscoverer(
            $this->getConnection(),
            $this->getContainer()->get(SettingsRepository::class),
        );
    }

    private function insertActivity(
        int $id,
        string $date,
        float $distanceKm,
        SportType $sportType = SportType::RIDE,
    ): void {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withDistance(Kilometer::from($distanceKm))
                ->withSportType($sportType)
                ->build(), []
        ));
    }
}
