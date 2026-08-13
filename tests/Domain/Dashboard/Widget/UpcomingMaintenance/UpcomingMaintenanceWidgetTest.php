<?php

namespace App\Tests\Domain\Dashboard\Widget\UpcomingMaintenance;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\UpcomingMaintenance\UpcomingMaintenanceWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideGearMaintenanceConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Yaml\Yaml;

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

        $this->assertMatchesHtmlSnapshot($render);
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

    public function testRenderDoesNotIncludeTasksThatWereNeverPerformed(): void
    {
        $this->importGearMaintenanceConfig();
        $this->provideMaintenanceHistory();

        $render = (string) $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()->add('numberOfTasksToDisplay', 5),
        );

        // "Clean" has no maintenance log, so there is no anchor date to count down from.
        $this->assertStringNotContainsString('Clean', $render);
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

        $this->assertNull($render);
    }

    public function testRenderWhenTheOnlyComponentIsAttachedToRetiredGear(): void
    {
        $this->importGearMaintenanceConfigAttachedToRetiredGearOnly();
        $this->provideMaintenanceHistory();

        $render = $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()->add('numberOfTasksToDisplay', 5),
        );

        $this->assertNull($render);
    }

    #[DataProvider(methodName: 'provideInvalidConfig')]
    public function testGuardValidConfigurationItShouldThrow(WidgetConfiguration $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidDashboardLayout($expectedException));
        $this->widget->guardValidConfiguration($config);
    }

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

    private function importGearMaintenanceConfigAttachedToRetiredGearOnly(): void
    {
        $this->getContainer()->get(KeyValueStore::class)->save(KeyValue::fromState(
            key: Key::GEAR_MAINTENANCE,
            value: Value::fromString(Json::encode(Yaml::parse(<<<YML
enabled: true
ignoreRetiredGear: true
components:
  - id: gearComponent-chain
    label: Some cool chain
    attachedTo:
      - retired
    maintenance:
      - id: maintenanceTask-chain-lubed
        label: Lube
        interval:
          value: 500
          unit: km
YML))),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = $this->getContainer()->get(UpcomingMaintenanceWidget::class);
    }
}
