<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Milestone\Context\GearElevationContext;
use App\Domain\Milestone\Discoverer\GearElevationMilestoneDiscoverer;
use App\Domain\Milestone\FunComparison\ElevationFunComparison;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\PreviousMilestone;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\Length\Foot;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;

class GearElevationMilestoneDiscovererTest extends ContainerTestCase
{
    private GearElevationMilestoneDiscoverer $discoverer;
    private MilestoneIdFactory $milestoneIdFactory;

    public function testDiscoverWithNoActivities(): void
    {
        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
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

        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverFirstThreshold(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($gearId)
                ->withName('Canyon Endurace')
                ->build()
        );
        $this->insertActivity('1', '2024-01-01', $gearId, 500.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_ELEVATION,
                    context: new GearElevationContext(gearName: 'Canyon Endurace', threshold: Meter::from(500.0)),
                )->withFunComparison(ElevationFunComparison::EMPIRE_STATE_BUILDING),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverMultipleThresholdsWithPreviousChain(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($gearId)
                ->withName('Canyon Endurace')
                ->build()
        );
        $this->insertActivity('1', '2024-01-01', $gearId, 1500.0);
        $this->insertActivity('2', '2024-01-02', $gearId, 1500.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_ELEVATION,
                    context: new GearElevationContext(gearName: 'Canyon Endurace', threshold: Meter::from(500.0)),
                )->withFunComparison(ElevationFunComparison::EMPIRE_STATE_BUILDING),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_ELEVATION,
                    context: new GearElevationContext(gearName: 'Canyon Endurace', threshold: Meter::from(1000.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Meter::from(500.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(ElevationFunComparison::TWO_EIFFEL_TOWERS),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::GEAR_ELEVATION,
                    context: new GearElevationContext(gearName: 'Canyon Endurace', threshold: Meter::from(2500.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-2'),
                    threshold: Meter::from(1000.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(ElevationFunComparison::DEEPEST_CAVE),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverTracksGearsSeparately(): void
    {
        $bikeId = GearId::fromUnprefixed('bike-1');
        $shoesId = GearId::fromUnprefixed('shoes-1');
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($bikeId)
                ->withName('Canyon Endurace')
                ->build()
        );
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($shoesId)
                ->withName('Nike Pegasus')
                ->build()
        );

        $this->insertActivity('1', '2024-01-01', $bikeId, 500.0);
        $this->insertActivity('2', '2024-01-02', $shoesId, 500.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_ELEVATION,
                    context: new GearElevationContext(gearName: 'Canyon Endurace', threshold: Meter::from(500.0)),
                )->withFunComparison(ElevationFunComparison::EMPIRE_STATE_BUILDING),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::GEAR_ELEVATION,
                    context: new GearElevationContext(gearName: 'Nike Pegasus', threshold: Meter::from(500.0)),
                )->withFunComparison(ElevationFunComparison::EMPIRE_STATE_BUILDING),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverSkipsZeroElevation(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($gearId)
                ->withName('Canyon Endurace')
                ->build()
        );
        $this->insertActivity('1', '2024-01-01', $gearId, 0.0);

        $this->assertTrue($this->discoverer->discover($this->milestoneIdFactory)->isEmpty());
    }

    public function testDiscoverWithImperialUnits(): void
    {
        $gearId = GearId::fromUnprefixed('bike-1');
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId($gearId)
                ->withName('Canyon Endurace')
                ->build()
        );
        $this->insertActivity('1', '2024-01-01', $gearId, 500.0);

        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsName::UNIT_SYSTEM, 'imperial');
        $discoverer = new GearElevationMilestoneDiscoverer(
            $this->getConnection(),
            $settingsRepository,
        );
        $milestones = $discoverer->discover($this->milestoneIdFactory);

        $context = $milestones->getFirst()->getContext();
        $this->assertInstanceOf(GearElevationContext::class, $context);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_ELEVATION,
                    context: new GearElevationContext(gearName: 'Canyon Endurace', threshold: Foot::from(1000.0)),
                )->withFunComparison(ElevationFunComparison::EIFFEL_TOWER),
            ],
            $milestones->toArray(),
        );
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new GearElevationMilestoneDiscoverer(
            $this->getConnection(),
            $this->getContainer()->get(SettingsRepository::class),
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
