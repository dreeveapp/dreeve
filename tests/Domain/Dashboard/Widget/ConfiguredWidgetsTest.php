<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\Widget\ConfiguredWidget;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
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
            ['widget' => 'gearStats', 'width' => 50, 'config' => ['includeRetiredGear' => false]],
        ]);

        /** @var ConfiguredWidget[] $configuredWidgets */
        $configuredWidgets = iterator_to_array($this->configuredWidgets());

        $this->assertCount(1, $configuredWidgets);
        $configuredWidget = $configuredWidgets[0];
        $this->assertSame('Total hours spent per gear', $configuredWidget->getLabel());
        $this->assertSame(50, $configuredWidget->getWidth());
        $this->assertSame(6, $configuredWidget->getSpan());
        $this->assertTrue($configuredWidget->isConfigurable());
        $this->assertFalse($configuredWidget->getConfiguration()->get('includeRetiredGear'));
        $this->assertSame([], $configuredWidget->getConfiguration()->get('restrictToSportTypes'));
    }

    public function testAWidgetWithoutConfigurationIsNotConfigurable(): void
    {
        // introText exposes no configuration.
        $this->saveLayout([
            ['widget' => 'introText', 'width' => 66],
        ]);

        /** @var ConfiguredWidget[] $configuredWidgets */
        $configuredWidgets = iterator_to_array($this->configuredWidgets());

        $this->assertCount(1, $configuredWidgets);
        $this->assertFalse($configuredWidgets[0]->isConfigurable());
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
