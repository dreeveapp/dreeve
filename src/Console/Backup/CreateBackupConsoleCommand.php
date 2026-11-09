<?php

declare(strict_types=1);

namespace App\Console\Backup;

use App\Infrastructure\Console\ProvideConsoleIntro;
use App\Infrastructure\Process\ProcessFactory;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: CreateBackupConsoleCommand::NAME, description: 'Create a local backup of the database and storage files')]
final class CreateBackupConsoleCommand extends Command
{
    use ProvideConsoleIntro;

    public const string NAME = 'app:backup:create';
    public const string KEEP_OPTION = 'keep';
    private const int DEFAULT_KEEP = 7;
    private const string BACKUP_DIRECTORY = 'backups';
    private const string DATABASE_DIRECTORY = 'database';

    public function __construct(
        private readonly Connection $connection,
        private readonly KernelProjectDir $kernelProjectDir,
        private readonly Clock $clock,
        private readonly ProcessFactory $processFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            name: self::KEEP_OPTION,
            mode: InputOption::VALUE_REQUIRED,
            description: 'The number of backups to keep',
            default: self::DEFAULT_KEEP,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $this->outputConsoleIntro($output);

        $keep = filter_var($input->getOption(self::KEEP_OPTION), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $keep) {
            $output->error(sprintf('The --%s option must be a positive number', self::KEEP_OPTION));

            return Command::INVALID;
        }

        $storageDirectory = $this->kernelProjectDir.'/storage';
        $backupDirectory = $this->kernelProjectDir.'/'.self::BACKUP_DIRECTORY;

        $mounts = is_readable('/proc/self/mountinfo') ? (file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES) ?: []) : [];
        $backupDirectoryIsMounted = array_any($mounts, fn (string $mount): bool => (explode(' ', $mount)[4] ?? null) === $backupDirectory);
        if (!$backupDirectoryIsMounted) {
            $output->warning('The backups directory is not mounted as a volume. Backups will be lost when the container is recreated. See https://docs.dreeve.app/troubleshooting/faq/#backup--restore');
        }
        if (!is_dir($backupDirectory)) {
            mkdir($backupDirectory, 0755, true);
        }

        $backupName = 'dreeve-'.$this->clock->now()->format('Y-m-d-His');
        $stagingDirectory = $backupDirectory.'/'.$backupName;
        $databaseSnapshotPath = $stagingDirectory.'/'.self::DATABASE_DIRECTORY.'/dreeve.db';
        $backupPath = $stagingDirectory.'.tar.zst';
        mkdir(dirname($databaseSnapshotPath), 0755, true);

        try {
            $output->writeln('=> Creating a snapshot of the database');
            $this->connection->executeStatement('VACUUM INTO ?', [$databaseSnapshotPath]);

            $storageEntries = array_values(array_diff(scandir($storageDirectory) ?: [], ['.', '..', self::DATABASE_DIRECTORY]));
            $output->writeln('=> Archiving storage, this can take a while');
            $process = $this->processFactory->create([
                'tar', '--zstd', '-cf', $backupPath, '--exclude=files/logs',
                '-C', $stagingDirectory, self::DATABASE_DIRECTORY,
                '-C', $storageDirectory, ...$storageEntries,
            ]);
            $process->setTimeout(null);
            $process->run();

            if (!in_array($process->getExitCode(), [0, 1], true)) {
                if (file_exists($backupPath)) {
                    unlink($backupPath);
                }
                $output->error(sprintf(
                    "Could not create backup, tar exited with code %s:\n%s",
                    $process->getExitCode(),
                    trim($process->getErrorOutput()),
                ));

                return Command::FAILURE;
            }
        } finally {
            if (file_exists($databaseSnapshotPath)) {
                unlink($databaseSnapshotPath);
            }
            rmdir(dirname($databaseSnapshotPath));
            rmdir($stagingDirectory);
        }

        $backups = glob($backupDirectory.'/dreeve-*.tar.zst') ?: [];
        sort($backups);
        foreach (array_slice($backups, 0, max(0, count($backups) - $keep)) as $oldBackup) {
            unlink($oldBackup);
            $output->writeln(sprintf('=> Deleted old backup "%s"', self::BACKUP_DIRECTORY.'/'.basename($oldBackup)));
        }

        $output->writeln(sprintf(
            '=> Created backup "%s" (%.1f MB)',
            self::BACKUP_DIRECTORY.'/'.basename($backupPath),
            (int) filesize($backupPath) / 1024 / 1024,
        ));

        return Command::SUCCESS;
    }
}
