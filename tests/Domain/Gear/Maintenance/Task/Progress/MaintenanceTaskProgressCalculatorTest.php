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
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\Installation\UsageWindow;
use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgress;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Domain\Gear\Maintenance\Task\Progress\ProgressCalculationContext;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Gear\Maintenance\GearComponentBuilder;
use App\Tests\Infrastructure\Time\Clock\PausedClock;

class MaintenanceTaskProgressCalculatorTest extends ContainerTestCase
{
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
        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: false,
        );
        $gearComponentRepository = $this->getContainer()->get(GearComponentRepository::class);
        foreach (Json::decode((string) file_get_contents(__DIR__.'/fixtures/never-logged-tasks.json')) as $component) {
            $gearComponentRepository->add(GearComponent::fromArray($component));
        }
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
        $this->assertNotNull($newChainReplace);
        $this->assertNull($newChainReplace->getLastPerformedOn());
        $newChainReplaceProgress = $newChainReplace->getProgress();
        $this->assertNotNull($newChainReplaceProgress);
        $this->assertSame('40 km', $newChainReplaceProgress->getDescription());
        $newChainInspect = $statuses->getForTask(MaintenanceTaskId::fromUnprefixed('new-chain-inspect'));
        $this->assertNotNull($newChainInspect);
        $this->assertNotNull($newChainInspect->getProgress());

        $oldCassetteReplace = $statuses->getForTask(MaintenanceTaskId::fromUnprefixed('old-cassette-replace'));
        $this->assertNotNull($oldCassetteReplace);
        $oldCassetteReplaceProgress = $oldCassetteReplace->getProgress();
        $this->assertNotNull($oldCassetteReplaceProgress);
        $this->assertSame('140 km', $oldCassetteReplaceProgress->getDescription());
        $oldCassetteInspect = $statuses->getForTask(MaintenanceTaskId::fromUnprefixed('old-cassette-inspect'));
        $this->assertNotNull($oldCassetteInspect);
        $this->assertNull($oldCassetteInspect->getProgress());
    }

    public function testGetGearIdsThatHaveDueTasks(): void
    {
        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: true,
        );
        $this->getContainer()->get(GearComponentRepository::class)->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('chain'))
            ->withLabel('Some cool chain')
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g1233776')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g10130856')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('retired')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
                label: Name::fromString('Lube'),
                intervalValue: 500,
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
            ))
            ->build());
        $this->rideSinceTheChainWasLubed(Kilometer::from(600));

        // The chain is attached to two active gears and one retired one, which is ignored.
        $calculator = $this->getContainer()->get(MaintenanceTaskProgressCalculator::class);
        $this->assertEquals(
            [GearId::fromUnprefixed('g1233776'), GearId::fromUnprefixed('g10130856')],
            $calculator->getGearIdsThatHaveDueTasks($calculator->calculateStatuses())->toArray()
        );
    }

    public function testGetGearIdsThatHaveDueTasksDoesNotReturnDuplicates(): void
    {
        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: true,
        );
        $this->getContainer()->get(GearComponentRepository::class)->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('chain'))
            ->withLabel('Some cool chain')
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g1233776')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g10130856')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('retired')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
                label: Name::fromString('Lube'),
                intervalValue: 500,
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
            ))
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('chain-replaced'),
                label: Name::fromString('Replace'),
                intervalValue: 1000,
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
            ))
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('chain-cleaned'),
                label: Name::fromString('Clean'),
                intervalValue: 1000,
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
            ))
            ->build());
        // Enough to make the 500km lube, the 1000km replace and the 1000km clean all due at once.
        $this->rideSinceTheChainWasLubed(Kilometer::from(1200));

        $calculator = $this->getContainer()->get(MaintenanceTaskProgressCalculator::class);
        $this->assertEquals(
            [GearId::fromUnprefixed('g1233776'), GearId::fromUnprefixed('g10130856')],
            $calculator->getGearIdsThatHaveDueTasks($calculator->calculateStatuses())->toArray()
        );
    }

    public function testRetiredComponentsAreNotTracked(): void
    {
        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: true,
        );
        $gearComponentRepository = $this->getContainer()->get(GearComponentRepository::class);
        $gearComponentRepository->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('chain'))
            ->withLabel('Some cool chain')
            ->withIsRetired(true)
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g1233776')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g10130856')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('retired')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
                label: Name::fromString('Lube'),
                intervalValue: 500,
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
            ))
            ->build());
        $gearComponentRepository->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('di-2'))
            ->withLabel('DI2 Battery')
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g1233776')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g10130856')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('di-2-charged'),
                label: Name::fromString('Charge'),
                intervalValue: 11,
                intervalUnit: IntervalUnit::EVERY_X_HOURS_USED,
            ))
            ->build());
        $this->rideSinceTheChainWasLubed(Kilometer::from(600));

        $calculator = $this->getContainer()->get(MaintenanceTaskProgressCalculator::class);
        $this->assertNull($calculator->calculateStatuses()->getForTask(MaintenanceTaskId::fromUnprefixed('chain-lubed')));
        $this->assertNotNull($calculator->calculateStatuses()->getForTask(MaintenanceTaskId::fromUnprefixed('di-2-charged')));
        $this->assertEmpty($calculator->getGearIdsThatHaveDueTasks($calculator->calculateStatuses())->toArray());
    }

    public function testGetGearIdsThatHaveDueTasksWhenNothingIsDue(): void
    {
        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: true,
        );
        $this->getContainer()->get(GearComponentRepository::class)->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('chain'))
            ->withLabel('Some cool chain')
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g1233776')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g10130856')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('retired')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
                label: Name::fromString('Lube'),
                intervalValue: 500,
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
            ))
            ->build());
        $this->rideSinceTheChainWasLubed(Kilometer::from(10));

        $calculator = $this->getContainer()->get(MaintenanceTaskProgressCalculator::class);
        $this->assertEmpty($calculator->getGearIdsThatHaveDueTasks($calculator->calculateStatuses())->toArray());
    }

    public function testGetGearIdsThatHaveDueTasksWhenTheFeatureIsNotEnabled(): void
    {
        $this->rideSinceTheChainWasLubed(Kilometer::from(600));

        $calculator = $this->getContainer()->get(MaintenanceTaskProgressCalculator::class);
        $this->assertEmpty($calculator->getGearIdsThatHaveDueTasks($calculator->calculateStatuses())->toArray());
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
