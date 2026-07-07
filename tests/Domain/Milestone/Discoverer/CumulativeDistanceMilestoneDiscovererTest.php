<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\CumulativeDistanceContext;
use App\Domain\Milestone\Discoverer\CumulativeDistanceMilestoneDiscoverer;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Milestone\IncrementingMilestoneIdFactory;
use Spatie\Snapshots\MatchesSnapshots;

class CumulativeDistanceMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private CumulativeDistanceMilestoneDiscoverer $discoverer;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function testDiscoverFirstMetricThreshold(): void
    {
        $this->insertActivity(1, '2024-01-01', 100.0);

        $milestones = $this->discoverer->discover();

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(CumulativeDistanceContext::class, $context);
        $this->assertInstanceOf(Kilometer::class, $context->getThreshold());
        $this->assertEquals(100.0, $context->getThreshold()->toFloat());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverMultipleThresholds(): void
    {
        $this->insertActivity(1, '2024-01-01', 250.0);
        $this->insertActivity(2, '2024-01-02', 260.0);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverSkipsZeroDistance(): void
    {
        $this->insertActivity(1, '2024-01-01', 0.0);
        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function testDiscoverWithImperialUnits(): void
    {
        $this->insertActivity(1, '2024-01-01', 161.0);

        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsGroup::APPEARANCE, ['unitSystem' => 'imperial']);
        $discoverer = new CumulativeDistanceMilestoneDiscoverer(
            $this->getConnection(),
            $settingsRepository,
            new IncrementingMilestoneIdFactory(),
        );
        $milestones = $discoverer->discover();
        $this->assertGreaterThanOrEqual(2, count($milestones));
    }

    public function testDiscoverWithMultipleSportTypes(): void
    {
        $this->insertActivityWithSportType(1, '2024-01-01', 150.0, SportType::RIDE);
        $this->insertActivityWithSportType(2, '2024-01-02', 110.0, SportType::RUN);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testFunComparisonIsSet(): void
    {
        $this->insertActivity(1, '2024-01-01', 500.0);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->discoverer = new CumulativeDistanceMilestoneDiscoverer(
            $this->getConnection(),
            $this->getContainer()->get(SettingsRepository::class),
            new IncrementingMilestoneIdFactory(),
        );
    }

    private function insertActivity(int $id, string $date, float $distanceKm): void
    {
        $this->insertActivityWithSportType($id, $date, $distanceKm, SportType::RIDE);
    }

    private function insertActivityWithSportType(int $id, string $date, float $distanceKm, SportType $sportType): void
    {
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
