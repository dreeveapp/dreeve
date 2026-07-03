<?php

namespace App\Tests\Domain\Dashboard;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Dashboard\RenderedWidget;
use App\Domain\Dashboard\RenderedWidgets;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Infrastructure\Time\Clock\PausedClock;

class RenderedWidgetsTest extends ContainerTestCase
{
    public function testItRendersWidgets(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-03 00:00:00'))
                ->build(),
            []
        ));

        $this->saveLayout([
            ['id' => 'dashboardWidget-1', 'widget' => 'introText', 'width' => 66],
        ]);

        /** @var RenderedWidget[] $rendered */
        $rendered = iterator_to_array($this->renderedWidgets());

        $this->assertCount(1, $rendered);
        $this->assertSame(66, $rendered[0]->getWidth());
        $this->assertNotEmpty($rendered[0]->getRenderedHtml());
    }

    public function testItSkipsWidgetsThatRenderNothing(): void
    {
        $this->saveLayout([
            ['id' => 'dashboardWidget-1', 'widget' => 'gearStats', 'width' => 50],
        ]);

        $this->assertCount(0, $this->renderedWidgets()->getIterator());
    }

    private function renderedWidgets(): RenderedWidgets
    {
        return new RenderedWidgets(
            $this->getContainer()->get(ConfiguredWidgets::class),
            PausedClock::fromString('2026-01-09'),
        );
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
