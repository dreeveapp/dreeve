<?php

declare(strict_types=1);

namespace App\Console\Daemon;

use App\Infrastructure\Console\ProvideConsoleIntro;
use App\Infrastructure\Daemon\Daemon;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use App\Infrastructure\Logging\LoggableConsoleOutput;
use App\Infrastructure\Time\Clock\Clock;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[WithMonologChannel('daemon')]
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: 'app:daemon:run', description: 'Start SFS daemon')]
final class RunDaemonConsoleCommand extends Command
{
    use ProvideConsoleIntro;

    public function __construct(
        private readonly Clock $clock,
        private readonly Daemon $daemon,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, new LoggableConsoleOutput($output, $this->logger));

        $this->outputDaemonConsoleIntro($output, $this->clock);

        $this->daemon->setConsoleOutput($output);
        $this->daemon->clearStaleCronLocks();
        $this->daemon->configureCron();

        if (!empty($_ENV['DAEMON_DEBUG'])) {
            $this->daemon->addPeriodicDebugTimer();
        }

        return Command::SUCCESS;
    }
}
