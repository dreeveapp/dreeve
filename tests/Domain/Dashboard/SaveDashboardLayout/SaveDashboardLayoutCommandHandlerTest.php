<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\SaveDashboardLayout;

use App\Domain\Dashboard\SaveDashboardLayout\SaveDashboardLayout;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;

class SaveDashboardLayoutCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private KeyValueStore $keyValueStore;

    public function testItReordersAndReWidthsWhilePreservingConfig(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::DASHBOARD,
            Value::fromString(Json::encode([
                ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
                ['id' => 'dashboardWidget-b', 'widget' => 'streaks', 'width' => 33, 'config' => ['subtitle' => 'Keep it up', 'sportTypesToInclude' => ['Run']]],
                ['id' => 'dashboardWidget-c', 'widget' => 'eddington', 'width' => 33],
            ])),
        ));

        $this->commandBus->dispatch(SaveDashboardLayout::fromPayload([
            'layout' => [
                ['id' => 'dashboardWidget-c', 'width' => 100],
                ['id' => 'dashboardWidget-a', 'width' => 50],
                ['id' => 'dashboardWidget-b', 'width' => 66],
            ],
        ]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::DASHBOARD));

        $this->assertSame([
            ['id' => 'dashboardWidget-c', 'widget' => 'eddington', 'width' => 100],
            ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 50],
            ['id' => 'dashboardWidget-b', 'widget' => 'streaks', 'width' => 66, 'config' => ['subtitle' => 'Keep it up', 'sportTypesToInclude' => ['Run']]],
        ], $config);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
    }
}
