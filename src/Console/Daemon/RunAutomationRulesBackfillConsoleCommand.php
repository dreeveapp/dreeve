<?php

declare(strict_types=1);

namespace App\Console\Daemon;

use App\Application\AppIsNotReady;
use App\Application\AppStatusChecker;
use App\Domain\Automation\Backfill\AutomationRulesBackfillQueue;
use App\Domain\Automation\Backfill\RunAutomationRulesBackfill\RunAutomationRulesBackfill;
use App\Domain\Import\ImportMode;
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
#[AsCommand(name: RunAutomationRulesBackfillConsoleCommand::NAME, description: 'Apply automation rules to existing activities')]
final class RunAutomationRulesBackfillConsoleCommand extends Command
{
    public const string NAME = 'app:cron:run-automation-rules-backfill';

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly AppStatusChecker $appStatusChecker,
        private readonly AutomationRulesBackfillQueue $backfillQueue,
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
            $output->writeln('<comment>Automation rules are only available in file import mode</comment>');

            return Command::SUCCESS;
        }

        if (!$this->backfillQueue->isQueued()) {
            $output->writeln('No automation rules backfill queued...');

            return Command::SUCCESS;
        }

        try {
            $this->mutex->acquireLock('runAutomationRulesBackfill');
        } catch (LockIsAlreadyAcquired) {
            $output->writeln('<comment>Postponing backfill, another process is importing data.</comment>');

            return Command::SUCCESS;
        }

        try {
            $this->appStatusChecker->ensureIsReadyForFileImport();

            $this->commandBus->dispatch(new RunAutomationRulesBackfill($output));
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

        return Command::SUCCESS;
    }
}
