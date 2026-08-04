<?php

namespace App\Tests\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgress;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Domain\Gear\Maintenance\Task\Progress\ProgressCalculationContext;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideGearMaintenanceConfig;

class MaintenanceTaskProgressCalculatorTest extends ContainerTestCase
{
    use ProvideGearMaintenanceConfig;

    public function testCalculateProgress(): void
    {
        $this->assertEquals(
            MaintenanceTaskProgress::from(100, 'test'),
            new MaintenanceTaskProgressCalculator([
                new ProgressCalculationOne(),
                new ProgressCalculationTwo(),
            ],
                $this->getContainer()->get(GearMaintenanceRepository::class),
                $this->getContainer()->get(GearMaintenanceLogRepository::class),
                $this->getContainer()->get(GearIdRepository::class),
            )->calculateProgressFor(
                ProgressCalculationContext::from(
                    gearIds: GearIds::fromArray([GearId::fromUnprefixed('test')]),
                    lastTaggedOn: SerializableDateTime::fromString('2025-01-03'),
                    intervalUnit: IntervalUnit::EVERY_X_DAYS,
                    intervalValue: 4,
                )
            )
        );
    }

    public function testGetGearIdsThatHaveDueTasks(): void
    {
        $this->importGearMaintenanceConfig();
        $this->rideSinceTheChainWasLubed(Kilometer::from(600));

        // The chain is attached to two active gears and one retired one, which is ignored.
        $this->assertEquals(
            [GearId::fromUnprefixed('g1233776'), GearId::fromUnprefixed('g10130856')],
            $this->getCalculator()->getGearIdsThatHaveDueTasks()->toArray()
        );
    }

    public function testGetGearIdsThatHaveDueTasksDoesNotReturnDuplicates(): void
    {
        $this->importGearMaintenanceConfig();
        // Enough to make the 500km lube, the 1000km replace and the 1000km clean all due at once.
        $this->rideSinceTheChainWasLubed(Kilometer::from(1200));

        $this->assertEquals(
            [GearId::fromUnprefixed('g1233776'), GearId::fromUnprefixed('g10130856')],
            $this->getCalculator()->getGearIdsThatHaveDueTasks()->toArray()
        );
    }

    public function testGetGearIdsThatHaveDueTasksWhenNothingIsDue(): void
    {
        $this->importGearMaintenanceConfig();
        $this->rideSinceTheChainWasLubed(Kilometer::from(10));

        $this->assertEmpty($this->getCalculator()->getGearIdsThatHaveDueTasks()->toArray());
    }

    public function testGetGearIdsThatHaveDueTasksWhenTheFeatureIsNotEnabled(): void
    {
        $this->rideSinceTheChainWasLubed(Kilometer::from(600));

        $this->assertEmpty($this->getCalculator()->getGearIdsThatHaveDueTasks()->toArray());
    }

    private function getCalculator(): MaintenanceTaskProgressCalculator
    {
        return $this->getContainer()->get(MaintenanceTaskProgressCalculator::class);
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
            $this->getContainer()->get(GearMaintenanceLogRepository::class),
            $this->getContainer()->get(GearIdRepository::class),
        )->calculateProgressFor(
            ProgressCalculationContext::from(
                gearIds: GearIds::fromArray([GearId::fromUnprefixed('test')]),
                lastTaggedOn: SerializableDateTime::fromString('2025-01-03'),
                intervalUnit: IntervalUnit::EVERY_X_DAYS,
                intervalValue: 4,
            )
        );
    }
}
