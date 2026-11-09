<?php

namespace App\Tests\Console\Backup;

use App\Application\AppName;
use App\Application\AppVersion;
use App\Console\Backup\RestoreBackupConsoleCommand;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Process\SymfonyProcessFactory;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\Console\ConsoleCommandTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class RestoreBackupConsoleCommandTest extends ConsoleCommandTestCase
{
    private RenderCache $renderCache;
    private Filesystem $filesystem;
    private string $projectDirectory;
    private string $databaseDirectory;

    public function testExecute(): void
    {
        $this->filesystem->dumpFile($this->databaseDirectory.'/dreeve.db', 'live database');
        $this->filesystem->dumpFile($this->databaseDirectory.'/dreeve.db-wal', 'live wal');
        $this->filesystem->dumpFile($this->databaseDirectory.'/dreeve.db-shm', 'live shm');
        $this->filesystem->dumpFile($this->projectDirectory.'/storage/files/activities/activity-1.json', 'changed activity');
        $this->filesystem->dumpFile($this->projectDirectory.'/storage/files/activities/activity-2.json', 'newer activity');
        $this->filesystem->dumpFile($this->projectDirectory.'/storage/files/logs/daemon.log', 'log');
        $this->filesystem->dumpFile($this->projectDirectory.'/backup-content/database/dreeve.db', 'database snapshot');
        $this->filesystem->dumpFile($this->projectDirectory.'/backup-content/files/activities/activity-1.json', 'activity');
        $this->filesystem->dumpFile($this->projectDirectory.'/backup-content/files/activities/database/file.json', 'nested database directory');
        $this->filesystem->dumpFile($this->projectDirectory.'/backup-content/gear-maintenance/config.yaml', 'gear');
        $this->filesystem->mkdir($this->projectDirectory.'/backups');
        new Process([
            'tar', '--zstd', '-cf', $this->projectDirectory.'/backups/dreeve-2026-11-08-030000.tar.zst',
            '-C', $this->projectDirectory.'/backup-content', 'database', 'files', 'gear-maintenance',
        ])->mustRun();
        $this->renderCache->get(
            cacheKey: 'a-page',
            cacheability: Cacheability::for('a-page', CacheTags::of(RootCacheTag::ACTIVITIES)),
            callback: fn (): string => 'cached page',
        );

        $command = $this->getCommandInApplication(RestoreBackupConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'command' => $command->getName(),
            RestoreBackupConsoleCommand::FILE_ARGUMENT => 'dreeve-2026-11-08-030000.tar.zst',
        ]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString(AppName::LABEL.' '.AppVersion::getSemanticVersion(), $commandTester->getDisplay());
        $this->assertStringContainsString('Restoring overwrites the current database and storage files', $commandTester->getDisplay());
        $this->assertStringContainsString('=> Extracting the database', $commandTester->getDisplay());
        $this->assertStringContainsString('=> Restoring storage files, this can take a while', $commandTester->getDisplay());
        $this->assertStringContainsString('=> Replacing the database', $commandTester->getDisplay());
        $this->assertStringContainsString('=> Cleared the render cache', $commandTester->getDisplay());
        $this->assertStringContainsString('=> Restored backup "dreeve-2026-11-08-030000.tar.zst". Start the app and the daemon again to use it', $commandTester->getDisplay());

        $this->assertEquals(['dreeve.db'], array_values(array_diff(scandir($this->databaseDirectory) ?: [], ['.', '..'])));
        $this->assertStringEqualsFile($this->databaseDirectory.'/dreeve.db', 'database snapshot');
        $this->assertDirectoryDoesNotExist($this->projectDirectory.'/storage/database');
        $this->assertStringEqualsFile($this->projectDirectory.'/storage/files/activities/activity-1.json', 'activity');
        $this->assertStringEqualsFile($this->projectDirectory.'/storage/files/activities/activity-2.json', 'newer activity');
        $this->assertStringEqualsFile($this->projectDirectory.'/storage/files/activities/database/file.json', 'nested database directory');
        $this->assertStringEqualsFile($this->projectDirectory.'/storage/files/logs/daemon.log', 'log');
        $this->assertStringEqualsFile($this->projectDirectory.'/storage/gear-maintenance/config.yaml', 'gear');
        $this->assertEquals('fresh page', $this->renderCache->get(
            cacheKey: 'a-page',
            cacheability: Cacheability::for('a-page', CacheTags::of(RootCacheTag::ACTIVITIES)),
            callback: fn (): string => 'fresh page',
        )->getContent());
    }

    public function testExecuteWhenNotConfirmed(): void
    {
        $this->filesystem->dumpFile($this->databaseDirectory.'/dreeve.db', 'live database');
        $this->filesystem->dumpFile($this->projectDirectory.'/backups/dreeve-2026-11-08-030000.tar.zst', 'backup');

        $command = $this->getCommandInApplication(RestoreBackupConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['no']);
        $commandTester->execute([
            'command' => $command->getName(),
            RestoreBackupConsoleCommand::FILE_ARGUMENT => $this->projectDirectory.'/backups/dreeve-2026-11-08-030000.tar.zst',
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('=> Restore cancelled', $commandTester->getDisplay());
        $this->assertStringEqualsFile($this->databaseDirectory.'/dreeve.db', 'live database');
    }

    public function testExecuteWhenTheBackupIsInvalid(): void
    {
        $this->filesystem->dumpFile($this->databaseDirectory.'/dreeve.db', 'live database');
        $this->filesystem->dumpFile($this->projectDirectory.'/backups/dreeve-2026-11-08-030000.tar.zst', 'not a backup');

        $command = $this->getCommandInApplication(RestoreBackupConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'command' => $command->getName(),
            RestoreBackupConsoleCommand::FILE_ARGUMENT => $this->projectDirectory.'/backups/dreeve-2026-11-08-030000.tar.zst',
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Could not restore backup, tar exited with code 2:', $commandTester->getDisplay());
        $this->assertStringNotContainsString('=> Restoring storage files', $commandTester->getDisplay());
        $this->assertEquals(['dreeve.db'], array_values(array_diff(scandir($this->databaseDirectory) ?: [], ['.', '..'])));
        $this->assertStringEqualsFile($this->databaseDirectory.'/dreeve.db', 'live database');
    }

    public function testExecuteWhenTheBackupDoesNotExist(): void
    {
        $command = $this->getCommandInApplication(RestoreBackupConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            RestoreBackupConsoleCommand::FILE_ARGUMENT => 'dreeve-2026-11-08-030000.tar.zst',
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Backup "dreeve-2026-11-08-030000.tar.zst" not found', $commandTester->getDisplay());
        $this->assertDirectoryDoesNotExist($this->databaseDirectory);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
        $this->filesystem = new Filesystem();
        $this->projectDirectory = sys_get_temp_dir().'/'.uniqid('restore-', true);
        $this->databaseDirectory = $this->projectDirectory.'/data/database';
        $this->filesystem->mkdir($this->projectDirectory.'/storage');
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDirectory);

        parent::tearDown();
    }

    #[\Override]
    protected function getConsoleCommand(): Command
    {
        return new RestoreBackupConsoleCommand(
            kernelProjectDir: KernelProjectDir::fromString($this->projectDirectory),
            databaseDirectory: $this->databaseDirectory,
            processFactory: new SymfonyProcessFactory(),
            renderCache: $this->renderCache,
        );
    }
}
