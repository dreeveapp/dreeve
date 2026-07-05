<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\ResetDashboardLayoutToDefault;

use App\Domain\Dashboard\DashboardLayout;
use App\Domain\Dashboard\ResetDashboardLayoutToDefault\ResetDashboardLayoutToDefault;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;

class ResetDashboardLayoutToDefaultCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private KeyValueStore $keyValueStore;

    public function testItResetsTheLayoutToDefault(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::DASHBOARD,
            Value::fromString(Json::encode([
                ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
                ['id' => 'dashboardWidget-b', 'widget' => 'weeklyStats', 'width' => 100],
            ])),
        ));

        $this->commandBus->dispatch(ResetDashboardLayoutToDefault::fromPayload([]));

        $config = Json::decode((string) $this->keyValueStore->find(Key::DASHBOARD));

        $this->assertSame(DashboardLayout::default(), $config);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
    }
}
