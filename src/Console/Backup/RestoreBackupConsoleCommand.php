<?php

declare(strict_types=1);

namespace App\Console\Backup;

use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Console\ProvideConsoleIntro;
use App\Infrastructure\Process\ProcessFactory;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: RestoreBackupConsoleCommand::NAME, description: 'Restore the database and storage files from a local backup')]
final class RestoreBackupConsoleCommand extends Command
{
    use ProvideConsoleIntro;

    public const string NAME = 'app:backup:restore';
    public const string FILE_ARGUMENT = 'file';
    private const string BACKUP_DIRECTORY = 'backups';
    private const string DATABASE_DIRECTORY = 'database';

    public function __construct(
        private readonly KernelProjectDir $kernelProjectDir,
        #[Autowire('%env(DATABASE_DIRECTORY)%')]
        private readonly string $databaseDirectory,
        private readonly ProcessFactory $processFactory,
        private readonly RenderCache $renderCache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            name: self::FILE_ARGUMENT,
            mode: InputArgument::REQUIRED,
            description: 'The backup to restore, a file name in the backups directory or a path',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $this->outputConsoleIntro($output);

        $file = (string) $input->getArgument(self::FILE_ARGUMENT);
        $backupPath = is_file($file) ? $file : $this->kernelProjectDir.'/'.self::BACKUP_DIRECTORY.'/'.$file;
        if (!is_file($backupPath)) {
            $output->error(sprintf('Backup "%s" not found', $file));

            return Command::FAILURE;
        }

        if (!$output->confirm(
            question: 'Restoring overwrites the current database and storage files. Make sure the app and the daemon are stopped. Continue?',
            default: false,
        )) {
            $output->writeln('=> Restore cancelled');

            return Command::FAILURE;
        }

        $storageDirectory = $this->kernelProjectDir.'/storage';
        $databaseDirectory = rtrim($this->databaseDirectory, '/');
        $databasePath = $databaseDirectory.'/dreeve.db';
        $stagingDirectory = $databaseDirectory.'/restore-'.basename($backupPath, '.tar.zst');
        $databaseSnapshotPath = $stagingDirectory.'/'.self::DATABASE_DIRECTORY.'/dreeve.db';
        if (!is_dir($stagingDirectory)) {
            mkdir($stagingDirectory, 0755, true);
        }

        try {
            foreach ([
                '=> Extracting the database' => [
                    'tar', '--zstd', '-xf', $backupPath,
                    '-C', $stagingDirectory, self::DATABASE_DIRECTORY.'/dreeve.db',
                ],
                '=> Restoring storage files, this can take a while' => [
                    'tar', '--zstd', '-xf', $backupPath,
                    '-C', $storageDirectory, '--anchored', '--exclude='.self::DATABASE_DIRECTORY,
                ],
            ] as $step => $command) {
                $output->writeln($step);
                $process = $this->processFactory->create($command);
                $process->setTimeout(null);
                $process->run();

                if (0 !== $process->getExitCode()) {
                    $output->error(sprintf(
                        "Could not restore backup, tar exited with code %s:\n%s",
                        $process->getExitCode(),
                        trim($process->getErrorOutput()),
                    ));

                    return Command::FAILURE;
                }
            }

            $output->writeln('=> Replacing the database');
            rename($databaseSnapshotPath, $databasePath);
            foreach ([$databasePath.'-wal', $databasePath.'-shm'] as $staleDatabaseFile) {
                if (file_exists($staleDatabaseFile)) {
                    unlink($staleDatabaseFile);
                }
            }
        } finally {
            if (file_exists($databaseSnapshotPath)) {
                unlink($databaseSnapshotPath);
            }
            if (is_dir(dirname($databaseSnapshotPath))) {
                rmdir(dirname($databaseSnapshotPath));
            }
            rmdir($stagingDirectory);
        }

        $this->renderCache->clear();
        $output->writeln('=> Cleared the render cache');
        $output->writeln(sprintf(
            '=> Restored backup "%s". Start the app and the daemon again to use it',
            basename($backupPath),
        ));

        return Command::SUCCESS;
    }
}
