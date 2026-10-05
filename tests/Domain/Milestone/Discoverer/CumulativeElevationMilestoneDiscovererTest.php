<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\CumulativeElevationContext;
use App\Domain\Milestone\Discoverer\CumulativeElevationMilestoneDiscoverer;
use App\Domain\Milestone\FunComparison\ElevationFunComparison;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\PreviousMilestone;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class CumulativeElevationMilestoneDiscovererTest extends ContainerTestCase
{
    private CumulativeElevationMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverFirstMetricThreshold(): void
    {
        $this->insertActivity(1, '2024-01-01', 500.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_ELEVATION,
                    context: new CumulativeElevationContext(threshold: Meter::from(500.0)),
                )->withFunComparison(ElevationFunComparison::EMPIRE_STATE_BUILDING),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_ELEVATION,
                    context: new CumulativeElevationContext(threshold: Meter::from(500.0)),
                )->withSportType(SportType::RIDE)->withFunComparison(ElevationFunComparison::EMPIRE_STATE_BUILDING),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverMultipleThresholds(): void
    {
        $this->insertActivity(1, '2024-01-01', 600.0);
        $this->insertActivity(2, '2024-01-02', 500.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_ELEVATION,
                    context: new CumulativeElevationContext(threshold: Meter::from(500.0)),
                )->withFunComparison(ElevationFunComparison::EMPIRE_STATE_BUILDING),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_ELEVATION,
                    context: new CumulativeElevationContext(threshold: Meter::from(500.0)),
                )->withSportType(SportType::RIDE)->withFunComparison(ElevationFunComparison::EMPIRE_STATE_BUILDING),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_ELEVATION,
                    context: new CumulativeElevationContext(threshold: Meter::from(1000.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Meter::from(500.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(ElevationFunComparison::TWO_EIFFEL_TOWERS),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-4'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_ELEVATION,
                    context: new CumulativeElevationContext(threshold: Meter::from(1000.0)),
                )->withSportType(SportType::RIDE)->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-2'),
                    threshold: Meter::from(500.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(ElevationFunComparison::TWO_EIFFEL_TOWERS),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverSkipsZeroElevation(): void
    {
        $this->insertActivity(1, '2024-01-01', 0.0);
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverWithImperialUnits(): void
    {
        $this->insertActivity(1, '2024-01-01', 500.0);

        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsName::UNIT_SYSTEM, 'imperial');
        $discoverer = new CumulativeElevationMilestoneDiscoverer(
            $this->getConnection(),
            $settingsRepository,
        );
        $milestones = $discoverer->discover($this->milestoneIdFactory);

        $this->assertGreaterThanOrEqual(2, count($milestones));
    }

    public function testDiscoverWithMultipleSportTypes(): void
    {
        $this->insertActivity(1, '2024-01-01', 600.0, SportType::RIDE);
        $this->insertActivity(2, '2024-01-02', 500.0, SportType::RUN);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_ELEVATION,
                    context: new CumulativeElevationContext(threshold: Meter::from(500.0)),
                )->withFunComparison(ElevationFunComparison::EMPIRE_STATE_BUILDING),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_ELEVATION,
                    context: new CumulativeElevationContext(threshold: Meter::from(500.0)),
                )->withSportType(SportType::RIDE)->withFunComparison(ElevationFunComparison::EMPIRE_STATE_BUILDING),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_ELEVATION,
                    context: new CumulativeElevationContext(threshold: Meter::from(1000.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Meter::from(500.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(ElevationFunComparison::TWO_EIFFEL_TOWERS),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-4'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::CUMULATIVE_ELEVATION,
                    context: new CumulativeElevationContext(threshold: Meter::from(500.0)),
                )->withSportType(SportType::RUN)->withFunComparison(ElevationFunComparison::EMPIRE_STATE_BUILDING),
            ],
            $milestones->toArray(),
        );
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new CumulativeElevationMilestoneDiscoverer(
            $this->getConnection(),
            $this->getContainer()->get(SettingsRepository::class),
        );
    }

    private function insertActivity(
        int $id,
        string $date,
        float $elevationM,
        SportType $sportType = SportType::RIDE,
    ): void {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withElevation(Meter::from($elevationM))
                ->withSportType($sportType)
                ->build(), []
        ));
    }
}
