<?php

namespace App\Tests;

use App\Domain\Import\ImportMode;
use Carbon\Carbon;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\LocaleSwitcher;

abstract class ContainerTestCase extends KernelTestCase
{
    use ProvideSettings;

    protected static ?Connection $ourDbalConnection = null;

    private ?string $originalImportMode = null;

    protected function importMode(): ImportMode
    {
        return ImportMode::STRAVA_API;
    }

    protected function setUp(): void
    {
        $this->originalImportMode = $_ENV['IMPORT_MODE'] ?? null;
        $_SERVER['IMPORT_MODE'] = $_ENV['IMPORT_MODE'] = $this->importMode()->value;

        parent::setUp();

        if (!self::$ourDbalConnection instanceof Connection) {
            self::bootKernel();
            self::$ourDbalConnection = self::getContainer()->get(Connection::class);
        }

        // Empty file systems.
        /** @var \League\Flysystem\FilesystemOperator[] $fileSystems */
        $fileSystems = [
            // $this->getContainer()->get('default.storage'),
            $this->getContainer()->get('file.storage'),
            $this->getContainer()->get('build_html.storage'),
        ];

        foreach ($fileSystems as $fileSystem) {
            $fileSystem->deleteDirectory('/');
        }

        // Make sure every test is initialized with the default locale.
        /** @var LocaleSwitcher $localeSwitcher */
        $localeSwitcher = $this->getContainer()->get(LocaleSwitcher::class);
        $localeSwitcher->reset();
        Carbon::setLocale($localeSwitcher->getLocale());

        // Seed settings.
        $this->provideSettings();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (null === $this->originalImportMode) {
            unset($_SERVER['IMPORT_MODE'], $_ENV['IMPORT_MODE']);

            return;
        }
        $_SERVER['IMPORT_MODE'] = $_ENV['IMPORT_MODE'] = $this->originalImportMode;
    }

    protected function getConnection(): Connection
    {
        return self::$ourDbalConnection;
    }
}
