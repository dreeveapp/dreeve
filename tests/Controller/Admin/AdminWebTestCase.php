<?php

namespace App\Tests\Controller\Admin;

use App\Domain\Import\ImportMode;
use App\Tests\ProvideBuiltApp;
use App\Tests\ProvideSettings;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

abstract class AdminWebTestCase extends WebTestCase
{
    use ProvideSettings;
    use ProvideBuiltApp;

    protected const string ADMIN_USERNAME = 'admin';
    protected const string ADMIN_PASSWORD = 'admin-password';

    protected KernelBrowser $client;

    private ?string $originalImportMode;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Remember the suite-wide import mode so withImportMode() overrides can be undone in tearDown().
        $this->originalImportMode = $_ENV['IMPORT_MODE'] ?? null;

        $_SERVER['ADMIN_USERNAME'] = $_ENV['ADMIN_USERNAME'] = self::ADMIN_USERNAME;
        $_SERVER['ADMIN_PASSWORD_HASH'] = $_ENV['ADMIN_PASSWORD_HASH'] = password_hash(
            self::ADMIN_PASSWORD,
            PASSWORD_BCRYPT,
            ['cost' => 4],
        );

        $this->client = static::createClient();
        $this->client->disableReboot();

        // Seed the DB-backed settings baseline the whole suite relies on.
        $this->provideSettings();

        if ($this->shouldMarkAppAsBuilt()) {
            $this->markAppAsBuilt();
        }
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

    protected function adminUser(): InMemoryUser
    {
        return new InMemoryUser(
            self::ADMIN_USERNAME,
            (string) $_SERVER['ADMIN_PASSWORD_HASH'],
            ['ROLE_ADMIN'],
        );
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
