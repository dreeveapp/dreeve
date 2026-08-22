<?php

namespace App\Tests\Controller;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\ImportMode;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\ProvideSettings;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ControllerWebTestCase extends WebTestCase
{
    use ProvideSettings;

    protected KernelBrowser $client;

    private ?string $originalImportMode;
    private ?string $originalApiKey;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalImportMode = $_ENV['IMPORT_MODE'] ?? null;
        $this->originalApiKey = $_ENV['DREEVE_API_KEY'] ?? null;

        $this->prepareEnvironment();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->provideSettings();

        if ($this->shouldSeedActivity()) {
            $this->seedActivity();
        }
    }

    protected function prepareEnvironment(): void
    {
    }

    protected function shouldSeedActivity(): bool
    {
        return true;
    }

    protected function seedActivity(): void
    {
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

        // Superglobals outlive the kernel, and the suite runs order-randomized, so a test that
        // configures an API key would otherwise leak it into one asserting there is none.
        if (null === $this->originalApiKey) {
            unset($_SERVER['DREEVE_API_KEY'], $_ENV['DREEVE_API_KEY']);
        } else {
            $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = $this->originalApiKey;
        }

        parent::tearDown();
    }

    protected function withImportMode(ImportMode $importMode): void
    {
        $_SERVER['IMPORT_MODE'] = $_ENV['IMPORT_MODE'] = $importMode->value;

        $this->rebootClient();
    }

    protected function withApiKey(string $apiKey): void
    {
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = $apiKey;

        $this->rebootClient();
    }

    private function rebootClient(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->disableReboot();

        // Booting a new kernel wiped the in memory build storage, mark the app as built again.
        if ($this->shouldSeedActivity()) {
            $this->seedActivity();
        }
    }
}
