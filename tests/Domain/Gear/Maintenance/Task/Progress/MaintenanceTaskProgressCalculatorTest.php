<?php

namespace App\Tests\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Installation\UsageWindow;
use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgress;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Domain\Gear\Maintenance\Task\Progress\ProgressCalculationContext;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use App\Tests\ProvideGearMaintenanceConfig;

class MaintenanceTaskProgressCalculatorTest extends ContainerTestCase
{
    use ProvideGearMaintenanceConfig;

    public function testCalculateProgress(): void
    {
        $this->assertEquals(
            MaintenanceTaskProgress::from(100, 100, 'test', 'test'),
            new MaintenanceTaskProgressCalculator([
                new ProgressCalculationOne(),
                new ProgressCalculationTwo(),
            ],
                $this->getContainer()->get(GearMaintenanceRepository::class),
                $this->getContainer()->get(GearComponentRepository::class),
                $this->getContainer()->get(GearMaintenanceLogRepository::class),
                $this->getContainer()->get(GearIdRepository::class),
                PausedClock::on(SerializableDateTime::some()),
            )->calculateProgressFor(
                ProgressCalculationContext::from(
                    usageWindows: UsageWindows::fromArray([UsageWindow::create(gearId: GearId::fromUnprefixed('test'), from: null, until: null, sportTypes: SportTypes::empty())]),
                    lastTaggedOn: SerializableDateTime::fromString('2025-01-03'),
                    intervalUnit: IntervalUnit::EVERY_X_DAYS,
                    intervalValue: 4,
                )
            )
        );
    }

    public function testCalculateStatusesForTasksThatWereNeverPerformed(): void
    {
        $this->importGearMaintenanceConfigFromArray(Json::decode(file_get_contents(__DIR__.'/fixtures/never-logged-tasks.json')));
        foreach ([['before-install', '2024-12-01 10:00:00', 100], ['after-install', '2025-02-01 10:00:00', 40]] as [$activityId, $startDateTime, $distance]) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withGearId(GearId::fromUnprefixed('bike-a'))
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->withDistance(Kilometer::from($distance))
                    ->build(),
                []
            ));
        }

        $statuses = $this->getContainer()->get(MaintenanceTaskProgressCalculator::class)->calculateStatuses();

        $newChainReplace = $statuses->getForTask(MaintenanceTaskId::fromUnprefixed('new-chain-replace'));
        $this->assertNull($newChainReplace->getLastPerformedOn());
        $this->assertSame('40 km', $newChainReplace->getProgress()->getDescription());
        $this->assertNotNull($statuses->getForTask(MaintenanceTaskId::fromUnprefixed('new-chain-inspect'))->getProgress());

        $this->assertSame('140 km', $statuses->getForTask(MaintenanceTaskId::fromUnprefixed('old-cassette-replace'))->getProgress()->getDescription());
        $this->assertNull($statuses->getForTask(MaintenanceTaskId::fromUnprefixed('old-cassette-inspect'))->getProgress());
    }

    public function testGetGearIdsThatHaveDueTasks(): void
    {
        $this->importGearMaintenanceConfig();
        $this->rideSinceTheChainWasLubed(Kilometer::from(600));

        // The chain is attached to two active gears and one retired one, which is ignored.
        $this->assertEquals(
            [GearId::fromUnprefixed('g1233776'), GearId::fromUnprefixed('g10130856')],
            $this->getContainer()->get(MaintenanceTaskProgressCalculator::class)->getGearIdsThatHaveDueTasks()->toArray()
        );
    }

    public function testGetGearIdsThatHaveDueTasksDoesNotReturnDuplicates(): void
    {
        $this->importGearMaintenanceConfig();
        // Enough to make the 500km lube, the 1000km replace and the 1000km clean all due at once.
        $this->rideSinceTheChainWasLubed(Kilometer::from(1200));

        $this->assertEquals(
            [GearId::fromUnprefixed('g1233776'), GearId::fromUnprefixed('g10130856')],
            $this->getContainer()->get(MaintenanceTaskProgressCalculator::class)->getGearIdsThatHaveDueTasks()->toArray()
        );
    }

    public function testRetiredComponentsAreNotTracked(): void
    {
        $this->importGearMaintenanceConfig();
        $gearComponentRepository = $this->getContainer()->get(GearComponentRepository::class);
        $chain = Json::decode(Json::encode($gearComponentRepository->find(GearComponentId::fromString('gearComponent-chain'))));
        $gearComponentRepository->update(GearComponent::fromArray([...$chain, 'isRetired' => true]));
        $this->rideSinceTheChainWasLubed(Kilometer::from(600));

        $calculator = $this->getContainer()->get(MaintenanceTaskProgressCalculator::class);
        $this->assertNull($calculator->calculateStatuses()->getForTask(MaintenanceTaskId::fromUnprefixed('chain-lubed')));
        $this->assertNotNull($calculator->calculateStatuses()->getForTask(MaintenanceTaskId::fromUnprefixed('di-2-charged')));
        $this->assertEmpty($calculator->getGearIdsThatHaveDueTasks()->toArray());
    }

    public function testGetGearIdsThatHaveDueTasksWhenNothingIsDue(): void
    {
        $this->importGearMaintenanceConfig();
        $this->rideSinceTheChainWasLubed(Kilometer::from(10));

        $this->assertEmpty($this->getContainer()->get(MaintenanceTaskProgressCalculator::class)->getGearIdsThatHaveDueTasks()->toArray());
    }

    public function testGetGearIdsThatHaveDueTasksWhenTheFeatureIsNotEnabled(): void
    {
        $this->rideSinceTheChainWasLubed(Kilometer::from(600));

        $this->assertEmpty($this->getContainer()->get(MaintenanceTaskProgressCalculator::class)->getGearIdsThatHaveDueTasks()->toArray());
    }

    private function rideSinceTheChainWasLubed(Kilometer $distance): void
    {
        $gearRepository = $this->getContainer()->get(GearRepository::class);
        foreach (['g1233776', 'g10130856'] as $gearId) {
            $gearRepository->add(GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed($gearId))
                ->withIsRetired(false)
                ->build());
        }
        $gearRepository->add(GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('retired'))
            ->withIsRetired(true)
            ->build());

        $this->getContainer()->get(GearMaintenanceLogRepository::class)->add(GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g1233776'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('ride-since-the-chain-was-lubed'))
                ->withGearId(GearId::fromUnprefixed('g1233776'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-02 00:00:00'))
                ->withDistance($distance)
                ->build(),
            []
        ));
    }

    public function testCalculateProgressForItShouldThrow(): void
    {
        $this->expectExceptionObject(new \RuntimeException('No progress calculation found for interval unit: days'));

        new MaintenanceTaskProgressCalculator(
            [],
            $this->getContainer()->get(GearMaintenanceRepository::class),
            $this->getContainer()->get(GearComponentRepository::class),
            $this->getContainer()->get(GearMaintenanceLogRepository::class),
            $this->getContainer()->get(GearIdRepository::class),
            PausedClock::on(SerializableDateTime::some()),
        )->calculateProgressFor(
            ProgressCalculationContext::from(
                usageWindows: UsageWindows::fromArray([UsageWindow::create(gearId: GearId::fromUnprefixed('test'), from: null, until: null, sportTypes: SportTypes::empty())]),
                lastTaggedOn: SerializableDateTime::fromString('2025-01-03'),
                intervalUnit: IntervalUnit::EVERY_X_DAYS,
                intervalValue: 4,
            )
        );
    }
}
