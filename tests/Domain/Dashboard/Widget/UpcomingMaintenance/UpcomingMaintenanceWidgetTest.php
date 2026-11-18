<?php

namespace App\Tests\Domain\Dashboard\Widget\UpcomingMaintenance;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\UpcomingMaintenance\UpcomingMaintenanceWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Gear\Maintenance\GearComponentBuilder;
use App\Tests\ProvideGearMaintenanceConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class UpcomingMaintenanceWidgetTest extends ContainerTestCase
{
    use ProvideGearMaintenanceConfig;
    use MatchesSnapshots;

    private UpcomingMaintenanceWidget $widget;

    public function testRender(): void
    {
        $this->importGearMaintenanceConfig();
        $this->provideMaintenanceHistory();

        $render = $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()->add('numberOfTasksToDisplay', 5),
        );

        $this->assertMatchesHtmlSnapshot((string) $render);
    }

    public function testRenderOnlyKeepsTheMostUrgentTasks(): void
    {
        $this->importGearMaintenanceConfig();
        $this->provideMaintenanceHistory();

        $render = (string) $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()->add('numberOfTasksToDisplay', 1),
        );

        // The chain has been ridden 620km on a 500km lube interval, which outranks every other task.
        $this->assertStringContainsString('Overdue by 120 km', $render);
        $this->assertStringNotContainsString('Replace', $render);
        $this->assertStringNotContainsString('Charge', $render);
    }

    public function testRenderIncludesTasksThatWereNeverPerformed(): void
    {
        $this->importGearMaintenanceConfig();
        $this->provideMaintenanceHistory();

        $render = (string) $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()->add('numberOfTasksToDisplay', 5),
        );

        $this->assertStringContainsString('Some cool chain &middot; Clean', $render);
    }

    public function testRenderWhenTheFeatureIsNotEnabled(): void
    {
        $this->provideMaintenanceHistory();

        $render = $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()->add('numberOfTasksToDisplay', 5),
        );

        $this->assertNull($render);
    }

    public function testRenderWhenNoMaintenanceWasEverPerformed(): void
    {
        $this->importGearMaintenanceConfig();

        $render = $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()->add('numberOfTasksToDisplay', 5),
        );

        $this->assertStringContainsString('Some cool chain &middot; Lube', (string) $render);
        $this->assertStringContainsString('500 km left', (string) $render);
    }

    public function testRenderWhenTheOnlyComponentIsAttachedToRetiredGear(): void
    {
        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: true,
        );
        $this->getContainer()->get(GearComponentRepository::class)->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('chain'))
            ->withLabel('Some cool chain')
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
        $this->provideMaintenanceHistory();

        $render = $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()->add('numberOfTasksToDisplay', 5),
        );

        $this->assertNull($render);
    }

    #[DataProvider('provideInvalidConfig')]
    public function testGuardValidConfigurationItShouldThrow(WidgetConfiguration $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidDashboardLayout($expectedException));
        $this->widget->guardValidConfiguration($config);
    }

    /**
     * @return iterable<string, array{WidgetConfiguration, string}>
     */
    public static function provideInvalidConfig(): iterable
    {
        yield 'missing "numberOfTasksToDisplay" key' => [WidgetConfiguration::empty(), 'Configuration item "numberOfTasksToDisplay" is required for UpcomingMaintenanceWidget.'];
        yield 'non integer "numberOfTasksToDisplay" key' => [WidgetConfiguration::empty()->add('numberOfTasksToDisplay', 'lol'), 'Configuration item "numberOfTasksToDisplay" must be an integer.'];
        yield 'too small "numberOfTasksToDisplay" key' => [WidgetConfiguration::empty()->add('numberOfTasksToDisplay', 0), 'Configuration item "numberOfTasksToDisplay" must be set to a value of 1 or greater.'];
    }

    private function provideMaintenanceHistory(): void
    {
        $gearRepository = $this->getContainer()->get(GearRepository::class);
        $gearRepository->add(GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('g1233776'))
            ->withIsRetired(false)
            ->build());
        $gearRepository->add(GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('retired'))
            ->withIsRetired(true)
            ->build());

        $gearMaintenanceLogRepository = $this->getContainer()->get(GearMaintenanceLogRepository::class);
        foreach (['chain-lubed', 'chain-replaced', 'di-2-charged'] as $maintenanceTaskId) {
            $gearMaintenanceLogRepository->add(GearMaintenanceLog::create(
                gearId: GearId::fromUnprefixed('g1233776'),
                maintenanceTaskId: MaintenanceTaskId::fromUnprefixed($maintenanceTaskId),
                performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
            ));
        }

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('ride-since-the-last-maintenance'))
                ->withGearId(GearId::fromUnprefixed('g1233776'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-02 00:00:00'))
                ->withDistance(Kilometer::from(620))
                ->withMovingTimeInSeconds(7200)
                ->build(),
            []
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = $this->getContainer()->get(UpcomingMaintenanceWidget::class);
    }
}
