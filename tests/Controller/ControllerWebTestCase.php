<?php

namespace App\Tests\Controller;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\ImportMode;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Twig\HtmlTwigExtension;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\ProvideSettings;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ControllerWebTestCase extends WebTestCase
{
    use ProvideSettings;

    protected KernelBrowser $client;

    private ?string $originalImportMode;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalImportMode = $_ENV['IMPORT_MODE'] ?? null;

        HtmlTwigExtension::$seenIds = [];

        $this->prepareEnvironment();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->provideSettings();

        if ($this->shouldMarkAppAsBuilt()) {
            $this->markAppAsBuilt();
        }
    }

    protected function prepareEnvironment(): void
    {
    }

    protected function shouldMarkAppAsBuilt(): bool
    {
        return true;
    }

    protected function markAppAsBuilt(): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILD_SNAPSHOT,
            value: Value::fromString('2023-10-17@1.0.0'),
        ));

        /** @var ActivityIdRepository $activityIdRepository */
        $activityIdRepository = $this->getContainer()->get(ActivityIdRepository::class);
        if ($activityIdRepository->count() > 0) {
            return;
        }

        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->build(),
            [],
        ));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (null === $this->originalImportMode) {
            unset($_SERVER['IMPORT_MODE'], $_ENV['IMPORT_MODE']);
        } else {
            $_SERVER['IMPORT_MODE'] = $_ENV['IMPORT_MODE'] = $this->originalImportMode;
        }

        parent::tearDown();
    }

    protected function withImportMode(ImportMode $importMode): void
    {
        $_SERVER['IMPORT_MODE'] = $_ENV['IMPORT_MODE'] = $importMode->value;

        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->disableReboot();

        // Booting a new kernel wiped the in memory build storage, mark the app as built again.
        if ($this->shouldMarkAppAsBuilt()) {
            $this->markAppAsBuilt();
        }
    }
}
