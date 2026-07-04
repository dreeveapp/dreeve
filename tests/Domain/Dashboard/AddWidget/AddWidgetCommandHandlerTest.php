<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\AddWidget;

use App\Domain\Dashboard\AddWidget\AddWidget;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;

class AddWidgetCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private KeyValueStore $keyValueStore;

    public function testItAppendsTheWidgetToTheLayout(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::DASHBOARD,
            Value::fromString(Json::encode([
                ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
                ['id' => 'dashboardWidget-b', 'widget' => 'weeklyStats', 'width' => 100],
            ])),
        ));

        $this->commandBus->dispatch(AddWidget::fromPayload([
            'widget' => 'eddington',
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::DASHBOARD));

        $this->assertSame(['introText', 'weeklyStats', 'eddington'], array_column($config, 'widget'));

        $added = $config[2];
        $this->assertSame('eddington', $added['widget']);
        $this->assertSame(50, $added['width']);
        $this->assertStringStartsWith('dashboardWidget-', $added['id']);
    }

    public function testItRejectsAnUnknownWidget(): void
    {
        $this->expectExceptionObject(CouldNotProcessCommand::withReason('Dashboard widget "doesNotExist" does not exist.'));

        $this->commandBus->dispatch(AddWidget::fromPayload([
            'widget' => 'doesNotExist',
        ]));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
    }
}
