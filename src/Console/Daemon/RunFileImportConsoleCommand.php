<?php

declare(strict_types=1);

namespace App\Console\Daemon;

use App\Application\AppIsNotReady;
use App\Application\AppStatusChecker;
use App\Application\AppUrl;
use App\Application\Import\CalculateActivityMetrics\CalculateActivityMetrics;
use App\Application\Import\FileImport\ImportActivityFiles\ImportActivityFiles;
use App\Domain\Import\ImportMode;
use App\Domain\Import\WatchDirectory;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use App\Infrastructure\Logging\LoggableConsoleOutput;
use App\Infrastructure\Mutex\LockIsAlreadyAcquired;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Time\ResourceUsage\ResourceUsage;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[WithMonologChannel('daemon')]
#[WithMutex(lockName: LockName::IMPORT_DATA)]
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: RunFileImportConsoleCommand::NAME, description: 'Run file import')]
final class RunFileImportConsoleCommand extends Command
{
    public const string NAME = 'app:cron:run-file-import';

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly AppStatusChecker $appStatusChecker,
        private readonly WatchDirectory $watchDirectory,
        private readonly ResourceUsage $resourceUsage,
        private readonly Mutex $mutex,
        private readonly AppUrl $appUrl,
        private readonly LoggerInterface $logger,
        private readonly ImportMode $importMode,
        private readonly SettingsRepository $settingsRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('import', null, InputOption::VALUE_NONE);
        $this->addOption('build', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, new LoggableConsoleOutput($output, $this->logger));

        if (!$this->importMode->isFiles()) {
            $output->writeln('<comment>Cannot import files. IMPORT_MODE=stravaApi</comment>');

            return Command::SUCCESS;
        }

        if (!$this->watchDirectory->hasFilesThatCanBeProcessed()) {
            $output->writeln('No files left to process...');

            return Command::SUCCESS;
        }

        $this->resourceUsage->startTimer();

        try {
            $this->mutex->acquireLock('runFileImport');
        } catch (LockIsAlreadyAcquired) {
            // Another process is importing data, postpone import.
            $output->writeln('<comment>Postponing file import, another process is importing data.</comment>');

            return Command::SUCCESS;
        }

        try {
            $this->appStatusChecker->ensureIsReadyForFileImport();

            $this->commandBus->dispatch(new ImportActivityFiles($output));
            $this->commandBus->dispatch(new CalculateActivityMetrics($output));
        } catch (AppIsNotReady $e) {
            $this->mutex->releaseLock();
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->mutex->releaseLock();
            throw $e;
        }

        $this->mutex->releaseLock();

        $this->resourceUsage->stopTimer();
        if ($this->settingsRepository->integrations()->shouldNotifyOnSuccessfulImport()) {
            $this->commandBus->dispatch(new SendNotification(
                title: 'Import successful',
                message: sprintf('New import of your stats was successful in %ss', $this->resourceUsage->getRunTimeInSeconds()),
                tags: ['+1'],
                actionUrl: $this->appUrl
            ));
        }

        $output->writeln(sprintf(
            '<info>%s</info>',
            $this->resourceUsage->format(),
        ));

        return Command::SUCCESS;
    }
}
