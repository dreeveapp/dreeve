<?php

declare(strict_types=1);

namespace App\Console\Import;

use App\Domain\Import\ImportMode;
use App\Domain\Import\RefreshFromFiles\ActivityFieldsToRefresh;
use App\Domain\Import\RefreshFromFiles\RefreshActivitiesFromFiles;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use App\Infrastructure\Logging\LoggableConsoleOutput;
use App\Infrastructure\Mutex\LockIsAlreadyAcquired;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[WithMonologChannel('daemon')]
#[WithMutex(lockName: LockName::IMPORT_DATA)]
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: RefreshActivitiesFromFilesConsoleCommand::NAME, description: 'Refresh activity fields from stored activity files')]
final class RefreshActivitiesFromFilesConsoleCommand extends Command
{
    public const string NAME = 'app:import:refresh-activities-from-files';

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly ActivityFieldsToRefresh $activityFieldsToRefresh,
        private readonly Mutex $mutex,
        private readonly LoggerInterface $logger,
        private readonly ImportMode $importMode,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, new LoggableConsoleOutput($output, $this->logger));

        if (!$this->importMode->isFiles()) {
            $output->writeln('<comment>Refreshing activities from files is only available in file import mode</comment>');

            return Command::SUCCESS;
        }

        if (!$this->activityFieldsToRefresh->hasAny()) {
            $output->writeln('No activity fields to refresh...');

            return Command::SUCCESS;
        }

        try {
            $this->mutex->acquireLock('refreshActivitiesFromFiles');
        } catch (LockIsAlreadyAcquired) {
            $output->writeln('<comment>Postponing refresh until the next daemon start, another process is importing data.</comment>');

            return Command::SUCCESS;
        }

        try {
            $this->commandBus->dispatch(new RefreshActivitiesFromFiles($output));
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->mutex->releaseLock();
            throw $e;
        }

        $this->mutex->releaseLock();

        return Command::SUCCESS;
    }
}
