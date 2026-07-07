<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Milestone\Context\GearElevationContext;
use App\Domain\Milestone\Discoverer\GearElevationMilestoneDiscoverer;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Milestone\IncrementingMilestoneIdFactory;
use Spatie\Snapshots\MatchesSnapshots;

class GearElevationMilestoneDiscovererTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private GearElevationMilestoneDiscoverer $discoverer;

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
                ->withElevation(Meter::from(1000))
                ->build(), []
        ));

        $this->assertTrue($this->discoverer->discover()->isEmpty());
    }

    public function testDiscoverFirstThreshold(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->insertGear($gearId, 'Canyon Endurace');
        $this->insertActivity('1', '2024-01-01', $gearId, 500.0);

        $milestones = $this->discoverer->discover();

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(GearElevationContext::class, $context);
        $this->assertEquals('Canyon Endurace', $context->getGearName());
        $this->assertEquals(500.0, $context->getThreshold()->toFloat());

        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverMultipleThresholdsWithPreviousChain(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->insertGear($gearId, 'Canyon Endurace');
        $this->insertActivity('1', '2024-01-01', $gearId, 1500.0);
        $this->insertActivity('2', '2024-01-02', $gearId, 1500.0);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverTracksGearsSeparately(): void
    {
        $bikeId = GearId::fromUnprefixed('bike-1');
        $shoesId = GearId::fromUnprefixed('shoes-1');
        $this->insertGear($bikeId, 'Canyon Endurace');
        $this->insertGear($shoesId, 'Nike Pegasus');

        $this->insertActivity('1', '2024-01-01', $bikeId, 500.0);
        $this->insertActivity('2', '2024-01-02', $shoesId, 500.0);

        $milestones = $this->discoverer->discover();
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function testDiscoverSkipsZeroElevation(): void
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
        $this->insertActivity('1', '2024-01-01', $gearId, 500.0);

        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsGroup::APPEARANCE, ['unitSystem' => 'imperial']);
        $discoverer = new GearElevationMilestoneDiscoverer(
            $this->getConnection(),
            $settingsRepository,
            new IncrementingMilestoneIdFactory(),
        );
        $milestones = $discoverer->discover();

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(GearElevationContext::class, $context);
        $this->assertMatchesJsonSnapshot(Json::encode($milestones));
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->discoverer = new GearElevationMilestoneDiscoverer(
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

    private function insertActivity(string $id, string $date, GearId $gearId, float $elevationM): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withStartDateTime(SerializableDateTime::fromString($date))
                ->withElevation(Meter::from($elevationM))
                ->withGearId($gearId)
                ->build(), []
        ));
    }
}
