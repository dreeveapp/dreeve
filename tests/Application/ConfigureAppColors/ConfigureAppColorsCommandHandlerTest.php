<?php

namespace App\Tests\Application\ConfigureAppColors;

use App\Application\ConfigureAppColors\ConfigureAppColors;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;

class ConfigureAppColorsCommandHandlerTest extends ContainerTestCase
{
    use ProvideTestData;
    private CommandBus $commandBus;

    public function testHandle(): void
    {
        $this->provideFullTestSet();

        $this->commandBus->dispatch(new ConfigureAppColors());
        $this->assertEquals(
            [
                'sportType' => ['Ride' => '#5470c6', 'VirtualRide' => '#91cc75', 'Run' => '#fac858'],
                'gear' => ['gear-b12659861' => '#5470c6', 'gear-b12659862' => '#91cc75', 'gear-b12659562' => '#fac858'],
            ],
            Json::decode(
                (string) $this->getContainer()->get(KeyValueStore::class)->find(Key::THEME)
            ),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
