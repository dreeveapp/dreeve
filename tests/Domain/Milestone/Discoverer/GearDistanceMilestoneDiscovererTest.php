<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Milestone\Context\GearDistanceContext;
use App\Domain\Milestone\Discoverer\GearDistanceMilestoneDiscoverer;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Milestone\IncrementingMilestoneIdFactory;
use Spatie\Snapshots\MatchesSnapshots;

class GearDistanceMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private GearDistanceMilestoneDiscoverer $discoverer;

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
                ->withDistance(Kilometer::from(200))
                ->build(), []
        ));

        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function testDiscoverFirstThreshold(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->insertGear($gearId, 'Canyon Endurace');
        $this->insertActivity('1', '2024-01-01', $gearId, 100.0);

        $milestones = $this->discoverer->discover();

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(GearDistanceContext::class, $context);
        $this->assertEquals('Canyon Endurace', $context->getGearName());
        $this->assertEquals(100.0, $context->getThreshold()->toFloat());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverMultipleThresholdsWithPreviousChain(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->insertGear($gearId, 'Canyon Endurace');
        $this->insertActivity('1', '2024-01-01', $gearId, 300.0);
        $this->insertActivity('2', '2024-01-02', $gearId, 250.0);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverTracksGearsSeparately(): void
    {
        $bikeId = GearId::fromUnprefixed('bike-1');
        $shoesId = GearId::fromUnprefixed('shoes-1');
        $this->insertGear($bikeId, 'Canyon Endurace');
        $this->insertGear($shoesId, 'Nike Pegasus');

        $this->insertActivity('1', '2024-01-01', $bikeId, 100.0);
        $this->insertActivity('2', '2024-01-02', $shoesId, 100.0);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverSkipsZeroDistance(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->insertGear($gearId, 'Canyon Endurace');
        $this->insertActivity('1', '2024-01-01', $gearId, 0.0);

        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function testDiscoverWithImperialUnits(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->insertGear($gearId, 'Canyon Endurace');
        $this->insertActivity('1', '2024-01-01', $gearId, 161.0);

        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsGroup::APPEARANCE, ['unitSystem' => 'imperial']);
        $discoverer = new GearDistanceMilestoneDiscoverer(
            $this->getConnection(),
            $settingsRepository,
            new IncrementingMilestoneIdFactory(),
        );
        $milestones = $discoverer->discover();

        $context = $milestones->toArray()[0]->getContext();
        $this->assertInstanceOf(GearDistanceContext::class, $context);

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->discoverer = new GearDistanceMilestoneDiscoverer(
            $this->getConnection(),
            $this->getContainer()->get(SettingsRepository::class),
            new IncrementingMilestoneIdFactory(),
        );
    }

    private function insertGear(GearId $gearId, string $name): void
    {
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($gearId)
                ->withName($name)
                ->build()
        );
    }

    private function insertActivity(string $id, string $date, GearId $gearId, float $distanceKm): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withDistance(Kilometer::from($distanceKm))
                ->withGearId($gearId)
                ->build(), []
        ));
    }
}
