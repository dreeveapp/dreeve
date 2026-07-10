<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Daemon;

use App\Infrastructure\Daemon\Daemon;
use Symfony\Component\Console\Output\OutputInterface;

final class FakeDaemon implements Daemon
{
    private OutputInterface $output;

    public function setConsoleOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getConsoleOutput(): OutputInterface
    {
        return $this->output;
    }

    public function addPeriodicDebugTimer(): void
    {
        $this->output->writeln('Periodic timer added');
    }

    public function clearStaleCronLocks(): void
    {
    }

    public function configureCron(): void
    {
        $this->output->writeln('Cron configured');
    }
}
