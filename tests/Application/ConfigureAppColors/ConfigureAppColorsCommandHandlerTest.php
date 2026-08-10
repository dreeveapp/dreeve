<?php

namespace App\Tests\Application\ConfigureAppColors;

use App\Application\ConfigureAppColors\ConfigureAppColors;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ConfigureAppColorsCommandHandlerTest extends ContainerTestCase
{
    use ProvideTestData;
    use MatchesSnapshots;

    private CommandBus $commandBus;

    public function testHandle(): void
    {
        $this->provideFullTestSet();

        $this->commandBus->dispatch(new ConfigureAppColors());
        $this->assertMatchesJsonSnapshot(Json::decode(
            (string) $this->getContainer()->get(KeyValueStore::class)->find(Key::THEME)
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
