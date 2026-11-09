<?php

namespace App\Tests\Console\Backup;

use App\Application\AppName;
use App\Application\AppVersion;
use App\Console\Backup\CreateBackupConsoleCommand;
use App\Infrastructure\Process\ProcessFactory;
use App\Infrastructure\Process\SymfonyProcessFactory;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class CreateBackupConsoleCommandTest extends ConsoleCommandTestCase
{
    private Connection&MockObject $connection;
    private ProcessFactory $processFactory;
    private Filesystem $filesystem;
    private string $projectDirectory;

    public function testExecute(): void
    {
        $this->filesystem->dumpFile($this->projectDirectory.'/storage/database/dreeve.db', 'live database');
        $this->filesystem->dumpFile($this->projectDirectory.'/storage/database/dreeve.db-wal', 'live wal');
        $this->filesystem->dumpFile($this->projectDirectory.'/storage/files/activities/activity-1.json', 'activity');
        $this->filesystem->dumpFile($this->projectDirectory.'/storage/files/logs/daemon.log', 'log');
        $this->filesystem->dumpFile($this->projectDirectory.'/storage/gear-maintenance/config.yaml', 'gear');
        $this->filesystem->dumpFile($this->projectDirectory.'/backups/dreeve-2026-11-06-030000.tar.zst', 'oldest');
        $this->filesystem->dumpFile($this->projectDirectory.'/backups/dreeve-2026-11-07-030000.tar.zst', 'older');

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('VACUUM INTO ?', [$this->projectDirectory.'/backups/dreeve-2026-11-08-030000/database/dreeve.db'])
            ->willReturnCallback(function (string $sql, array $params): int {
                file_put_contents($params[0], 'database snapshot');

                return 0;
            });

        $command = $this->getCommandInApplication(CreateBackupConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--'.CreateBackupConsoleCommand::KEEP_OPTION => 2,
        ]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString(AppName::LABEL.' '.AppVersion::getSemanticVersion(), $commandTester->getDisplay());
        $this->assertStringContainsString('The backups directory is not mounted as a volume', $commandTester->getDisplay());
        $this->assertStringContainsString('=> Creating a snapshot of the database', $commandTester->getDisplay());
        $this->assertStringContainsString('=> Archiving storage, this can take a while', $commandTester->getDisplay());
        $this->assertStringContainsString('=> Deleted old backup "backups/dreeve-2026-11-06-030000.tar.zst"', $commandTester->getDisplay());
        $this->assertMatchesRegularExpression('/=> Created backup "backups\/dreeve-2026-11-08-030000.tar.zst" \(\d+\.\d MB\)/', $commandTester->getDisplay());
        $this->assertEquals(
            ['dreeve-2026-11-07-030000.tar.zst', 'dreeve-2026-11-08-030000.tar.zst'],
            array_values(array_diff(scandir($this->projectDirectory.'/backups') ?: [], ['.', '..'])),
        );

        $extractedTarPath = $this->projectDirectory.'/extracted.tar';
        file_put_contents($extractedTarPath, zstd_uncompress((string) file_get_contents($this->projectDirectory.'/backups/dreeve-2026-11-08-030000.tar.zst')));
        $archivedFiles = [];
        foreach (new \RecursiveIteratorIterator(new \PharData($extractedTarPath)) as $archivedFile) {
            /* @var \PharFileInfo $archivedFile */
            $archivedFiles[substr($archivedFile->getPathname(), strlen('phar://'.$extractedTarPath) + 1)] = $archivedFile->getContent();
        }
        ksort($archivedFiles);

        $this->assertEquals(
            [
                'database/dreeve.db' => 'database snapshot',
                'files/activities/activity-1.json' => 'activity',
                'gear-maintenance/config.yaml' => 'gear',
            ],
            $archivedFiles,
        );
    }

    public function testExecuteWhenTarFails(): void
    {
        $this->filesystem->dumpFile($this->projectDirectory.'/storage/files/activities/activity-1.json', 'activity');

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params): int {
                file_put_contents($params[0], 'database snapshot');

                return 0;
            });

        $process = $this->createStub(Process::class);
        $process->method('getExitCode')->willReturn(2);
        $process->method('getErrorOutput')->willReturn("tar: Cannot write: No space left on device\n");

        $backupPath = $this->projectDirectory.'/backups/dreeve-2026-11-08-030000.tar.zst';
        $processFactory = $this->createMock(ProcessFactory::class);
        $processFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'tar', '--zstd', '-cf', $backupPath, '--exclude=files/logs',
                '-C', $this->projectDirectory.'/backups/dreeve-2026-11-08-030000', 'database',
                '-C', $this->projectDirectory.'/storage', 'files',
            ])
            ->willReturnCallback(function () use ($backupPath, $process): Process {
                file_put_contents($backupPath, 'partial backup');

                return $process;
            });
        $this->processFactory = $processFactory;

        $command = $this->getCommandInApplication(CreateBackupConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('The backups directory is not mounted as a volume', $commandTester->getDisplay());
        $this->assertStringContainsString('Could not create backup, tar exited with code 2:', $commandTester->getDisplay());
        $this->assertStringContainsString('tar: Cannot write: No space left on device', $commandTester->getDisplay());
        $this->assertEquals([], array_values(array_diff(scandir($this->projectDirectory.'/backups') ?: [], ['.', '..'])));
    }

    public function testExecuteWhenTarReportsFilesChangedWhileReading(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params): int {
                file_put_contents($params[0], 'database snapshot');

                return 0;
            });

        $process = $this->createStub(Process::class);
        $process->method('getExitCode')->willReturn(1);

        $backupPath = $this->projectDirectory.'/backups/dreeve-2026-11-08-030000.tar.zst';
        $processFactory = $this->createMock(ProcessFactory::class);
        $processFactory
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(function () use ($backupPath, $process): Process {
                file_put_contents($backupPath, 'backup');

                return $process;
            });
        $this->processFactory = $processFactory;

        $command = $this->getCommandInApplication(CreateBackupConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('=> Created backup "backups/dreeve-2026-11-08-030000.tar.zst"', $commandTester->getDisplay());
        $this->assertFileExists($backupPath);
    }

    public function testExecuteWithAnInvalidKeepOption(): void
    {
        $this->connection
            ->expects($this->never())
            ->method('executeStatement');

        $command = $this->getCommandInApplication(CreateBackupConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--'.CreateBackupConsoleCommand::KEEP_OPTION => 0,
        ]);

        $this->assertEquals(Command::INVALID, $commandTester->getStatusCode());
        $this->assertStringContainsString('The --keep option must be a positive number', $commandTester->getDisplay());
        $this->assertDirectoryDoesNotExist($this->projectDirectory.'/backups');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);
        $this->processFactory = new SymfonyProcessFactory();
        $this->filesystem = new Filesystem();
        $this->projectDirectory = sys_get_temp_dir().'/'.uniqid('backup-', true);
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
        return new CreateBackupConsoleCommand(
            connection: $this->connection,
            kernelProjectDir: KernelProjectDir::fromString($this->projectDirectory),
            clock: PausedClock::fromString('2026-11-08 03:00:00'),
            processFactory: $this->processFactory,
        );
    }
}
