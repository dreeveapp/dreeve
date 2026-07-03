<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\DeleteWidget;

use App\Domain\Dashboard\DeleteWidget\DeleteWidget;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;

class DeleteWidgetCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private KeyValueStore $keyValueStore;

    public function testItDeletesWidgetAndPreservesOrderOfOthers(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::DASHBOARD,
            Value::fromString(Json::encode([
                ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
                ['id' => 'dashboardWidget-b', 'widget' => 'weeklyStats', 'width' => 100],
                ['id' => 'dashboardWidget-c', 'widget' => 'eddington', 'width' => 33],
            ])),
        ));

        $this->commandBus->dispatch(DeleteWidget::fromPayload([
            'dashboardWidgetId' => 'dashboardWidget-b',
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::DASHBOARD));

        $this->assertSame(
            ['dashboardWidget-a', 'dashboardWidget-c'],
            array_column($config, 'id'),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
    }
}
