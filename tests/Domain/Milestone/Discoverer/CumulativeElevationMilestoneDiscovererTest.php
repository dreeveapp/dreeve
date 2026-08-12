<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\CumulativeElevationContext;
use App\Domain\Milestone\Discoverer\CumulativeElevationMilestoneDiscoverer;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class CumulativeElevationMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

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

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(CumulativeElevationContext::class, $context);
        $this->assertInstanceOf(Meter::class, $context->getThreshold());
        $this->assertEquals(500.0, $context->getThreshold()->toFloat());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverMultipleThresholds(): void
    {
        $this->insertActivity(1, '2024-01-01', 600.0);
        $this->insertActivity(2, '2024-01-02', 500.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
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
        $settingsRepository->save(SettingsGroup::APPEARANCE, ['unitSystem' => 'imperial']);
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
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testFunComparisonIsSet(): void
    {
        $this->insertActivity(1, '2024-01-01', 1000.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
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
