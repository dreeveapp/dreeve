<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\ConfiguredWidget;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Domain\Dashboard\Widget\WidgetName;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;

class ConfiguredWidgetsTest extends ContainerTestCase
{
    public function testItYieldsConfiguredWidgetsWithMergedConfiguration(): void
    {
        $this->saveLayout([
            ['id' => 'dashboardWidget-fixed', 'widget' => 'gearStats', 'width' => 50, 'config' => ['includeRetiredGear' => false]],
        ]);

        /** @var ConfiguredWidget[] $configuredWidgets */
        $configuredWidgets = iterator_to_array($this->configuredWidgets());

        $this->assertCount(1, $configuredWidgets);
        $configuredWidget = $configuredWidgets[0];
        $this->assertSame('dashboardWidget-fixed', (string) $configuredWidget->getId());
        $this->assertSame('gearStats', (string) $configuredWidget->getName());
        $this->assertSame('Total hours spent per gear', $configuredWidget->getLabel());
        $this->assertSame(50, $configuredWidget->getWidth());
        $this->assertTrue($configuredWidget->isConfigurable());
        $this->assertFalse($configuredWidget->getConfiguration()->get('includeRetiredGear'));
        $this->assertSame([], $configuredWidget->getConfiguration()->get('restrictToSportTypes'));
    }

    public function testAWidgetWithoutConfigurationIsNotConfigurable(): void
    {
        $this->saveLayout([
            ['id' => 'dashboardWidget-1', 'widget' => 'introText', 'width' => 66],
        ]);

        /** @var ConfiguredWidget[] $configuredWidgets */
        $configuredWidgets = iterator_to_array($this->configuredWidgets());

        $this->assertCount(1, $configuredWidgets);
        $this->assertFalse($configuredWidgets[0]->isConfigurable());
    }

    public function testTrainingGoalsWidgetWithoutConfiguredGoalsStillNeedsConfiguration(): void
    {
        $this->saveLayout([
            ['id' => 'dashboardWidget-1', 'widget' => 'trainingGoals', 'width' => 50, 'config' => ['goals' => []]],
        ]);

        /** @var ConfiguredWidget[] $configuredWidgets */
        $configuredWidgets = iterator_to_array($this->configuredWidgets());

        $this->assertCount(1, $configuredWidgets);
        $this->assertTrue($configuredWidgets[0]->stillNeedsConfiguration());
    }

    public function testTrainingGoalsWidgetWithConfiguredGoalsDoesNotStillNeedConfiguration(): void
    {
        $this->saveLayout([
            ['id' => 'dashboardWidget-1', 'widget' => 'trainingGoals', 'width' => 50, 'config' => ['goals' => [
                'weekly' => [
                    ['label' => 'Ride 50km', 'type' => 'distance', 'unit' => 'km', 'goal' => 50, 'sportTypesToInclude' => ['Ride']],
                ],
            ]]],
        ]);

        /** @var ConfiguredWidget[] $configuredWidgets */
        $configuredWidgets = iterator_to_array($this->configuredWidgets());

        $this->assertCount(1, $configuredWidgets);
        $this->assertFalse($configuredWidgets[0]->stillNeedsConfiguration());
    }

    public function testWhenWidgetDoesNotExists(): void
    {
        $this->saveLayout([
            ['widget' => 'invalid', 'width' => 100],
        ]);

        $this->expectExceptionObject(new \InvalidArgumentException('Dashboard widget "invalid" does not exists.'));
        $this->configuredWidgets()->getIterator();
    }

    public function testWhenWidgetHasBeenRemoved(): void
    {
        $this->saveLayout([
            ['widget' => 'bestEfforts', 'width' => 100],
        ]);

        $this->assertCount(0, $this->configuredWidgets()->getIterator());
    }

    public function testFindReturnsTheMatchingWidget(): void
    {
        $this->saveLayout([
            ['id' => 'dashboardWidget-1', 'widget' => 'introText', 'width' => 66],
            ['id' => 'dashboardWidget-2', 'widget' => 'gearStats', 'width' => 50],
        ]);

        $configuredWidget = $this->configuredWidgets()->find(DashboardWidgetId::fromString('dashboardWidget-2'));

        $this->assertInstanceOf(ConfiguredWidget::class, $configuredWidget);
        $this->assertSame('dashboardWidget-2', (string) $configuredWidget->getId());
    }

    public function testFindReturnsNullWhenWidgetIsNotInTheLayout(): void
    {
        $this->saveLayout([
            ['id' => 'dashboardWidget-1', 'widget' => 'introText', 'width' => 66],
        ]);

        $this->assertNull(
            $this->configuredWidgets()->find(DashboardWidgetId::fromString('dashboardWidget-does-not-exist')),
        );
    }

    public function testGetAvailableWidgetsReturnsCatalogSortedByLabel(): void
    {
        $availableWidgets = $this->configuredWidgets()->getAvailableWidgets();

        $this->assertArrayHasKey('eddington', $availableWidgets);
        $this->assertArrayHasKey('gearStats', $availableWidgets);

        $labels = array_map(static fn ($widget): string => $widget->getLabel(), $availableWidgets);
        $sorted = $labels;
        uasort($sorted, strcasecmp(...));
        $this->assertSame(array_values($sorted), array_values($labels));
    }

    public function testHasAvailableWidget(): void
    {
        $configuredWidgets = $this->configuredWidgets();

        $this->assertTrue($configuredWidgets->hasAvailableWidget(WidgetName::fromConfigValue('eddington')));
        $this->assertFalse($configuredWidgets->hasAvailableWidget(WidgetName::fromConfigValue('doesNotExist')));
    }

    public function testGetConfiguredWidgetCountPerType(): void
    {
        $this->saveLayout([
            ['id' => 'dashboardWidget-1', 'widget' => 'introText', 'width' => 66],
            ['id' => 'dashboardWidget-2', 'widget' => 'gearStats', 'width' => 50],
            ['id' => 'dashboardWidget-3', 'widget' => 'gearStats', 'width' => 50],
        ]);

        $this->assertSame(
            ['introText' => 1, 'gearStats' => 2],
            $this->configuredWidgets()->getConfiguredWidgetCountPerType(),
        );
    }

    private function configuredWidgets(): ConfiguredWidgets
    {
        return $this->getContainer()->get(ConfiguredWidgets::class);
    }

    private function saveLayout(array $layout): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->save(KeyValue::fromState(
            key: Key::DASHBOARD,
            value: Value::fromString(Json::encode($layout)),
        ));
    }
}
