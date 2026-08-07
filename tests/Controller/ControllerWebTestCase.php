<?php

namespace App\Tests\Controller;

use App\Domain\Import\ImportMode;
use App\Tests\ProvideBuiltApp;
use App\Tests\ProvideSettings;
use App\Tests\ResetStaticCaches;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ControllerWebTestCase extends WebTestCase
{
    use ProvideSettings;
    use ProvideBuiltApp;
    use ResetStaticCaches;

    protected KernelBrowser $client;

    private ?string $originalImportMode;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalImportMode = $_ENV['IMPORT_MODE'] ?? null;

        $this->resetStaticCaches();

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
