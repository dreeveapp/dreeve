<?php

namespace App\Tests\Domain\Milestone\Discoverer;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Milestone\Context\GearDistanceContext;
use App\Domain\Milestone\Discoverer\GearDistanceMilestoneDiscoverer;
use App\Domain\Milestone\FunComparison\DistanceFunComparison;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneId;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\PreviousMilestone;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Mile;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;

class GearDistanceMilestoneDiscovererTest extends ContainerTestCase
{
    private GearDistanceMilestoneDiscoverer $discoverer;
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
                ->withDistance(Kilometer::from(200))
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
        $this->insertActivity('1', '2024-01-01', $gearId, 100.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_DISTANCE,
                    context: new GearDistanceContext(gearName: 'Canyon Endurace', threshold: Kilometer::from(100.0)),
                )->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
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
        $this->insertActivity('1', '2024-01-01', $gearId, 300.0);
        $this->insertActivity('2', '2024-01-02', $gearId, 250.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_DISTANCE,
                    context: new GearDistanceContext(gearName: 'Canyon Endurace', threshold: Kilometer::from(100.0)),
                )->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_DISTANCE,
                    context: new GearDistanceContext(gearName: 'Canyon Endurace', threshold: Kilometer::from(250.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-1'),
                    threshold: Kilometer::from(100.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(DistanceFunComparison::LENGTH_OF_JAMAICA),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-3'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::GEAR_DISTANCE,
                    context: new GearDistanceContext(gearName: 'Canyon Endurace', threshold: Kilometer::from(500.0)),
                )->withPrevious(PreviousMilestone::create(
                    previousMilestoneId: MilestoneId::fromString('milestone-2'),
                    threshold: Kilometer::from(250.0),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                ))->withFunComparison(DistanceFunComparison::MADRID_TO_BARCELONA),
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

        $this->insertActivity('1', '2024-01-01', $bikeId, 100.0);
        $this->insertActivity('2', '2024-01-02', $shoesId, 100.0);

        $milestones = $this->discoverer->discover($this->milestoneIdFactory);
        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_DISTANCE,
                    context: new GearDistanceContext(gearName: 'Canyon Endurace', threshold: Kilometer::from(100.0)),
                )->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
                Milestone::create(
                    id: MilestoneId::fromString('milestone-2'),
                    achievedOn: SerializableDateTime::fromString('2024-01-02 00:00:00'),
                    category: MilestoneCategory::GEAR_DISTANCE,
                    context: new GearDistanceContext(gearName: 'Nike Pegasus', threshold: Kilometer::from(100.0)),
                )->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
            ],
            $milestones->toArray(),
        );
    }

    public function testDiscoverSkipsZeroDistance(): void
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
        $this->insertActivity('1', '2024-01-01', $gearId, 161.0);

        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsName::UNIT_SYSTEM, 'imperial');
        $discoverer = new GearDistanceMilestoneDiscoverer(
            $this->getConnection(),
            $settingsRepository,
        );
        $milestones = $discoverer->discover($this->milestoneIdFactory);

        $this->assertEquals(
            [
                Milestone::create(
                    id: MilestoneId::fromString('milestone-1'),
                    achievedOn: SerializableDateTime::fromString('2024-01-01 00:00:00'),
                    category: MilestoneCategory::GEAR_DISTANCE,
                    context: new GearDistanceContext(gearName: 'Canyon Endurace', threshold: Mile::from(100.0)),
                )->withFunComparison(DistanceFunComparison::EDGE_OF_SPACE),
            ],
            $milestones->toArray(),
        );
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->milestoneIdFactory = new MilestoneIdFactory();
        $this->discoverer = new GearDistanceMilestoneDiscoverer(
            $this->getConnection(),
            $this->getContainer()->get(SettingsRepository::class),
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
