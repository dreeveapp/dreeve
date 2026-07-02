<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\DashboardLayoutRepository;
use App\Domain\Dashboard\Widget\Widgets;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\Time\Clock\PausedClock;

class WidgetsTest extends ContainerTestCase
{
    public function testWhenWidgetDoesNotExists(): void
    {
        $this->saveLayout([
            ['widget' => 'invalid', 'width' => 100, 'enabled' => true],
        ]);

        $widgets = new Widgets(
            widgets: [],
            dashboardLayoutRepository: $this->getContainer()->get(DashboardLayoutRepository::class),
            clock: PausedClock::fromString('2026-01-09')
        );

        $this->expectExceptionObject(new \InvalidArgumentException('Dashboard widget "invalid" does not exists.'));
        $widgets->getIterator();
    }

    public function testWhenWidgetHasBeenRemoved(): void
    {
        $this->saveLayout([
            ['widget' => 'bestEfforts', 'width' => 100, 'enabled' => true],
        ]);

        $widgets = new Widgets(
            widgets: [],
            dashboardLayoutRepository: $this->getContainer()->get(DashboardLayoutRepository::class),
            clock: PausedClock::fromString('2026-01-09')
        );

        $this->assertCount(
            0,
            $widgets->getIterator(),
        );
    }

    /**
     * @param list<array<string, mixed>> $layout
     */
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
