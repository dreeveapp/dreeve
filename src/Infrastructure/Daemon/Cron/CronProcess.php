<?php

declare(strict_types=1);

namespace App\Infrastructure\Daemon\Cron;

use App\Infrastructure\Time\Clock\Clock;
use React\ChildProcess\Process;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @codeCoverageIgnore
 */
final readonly class CronProcess
{
    public function __construct(
        private string $cronActionId,
        private Clock $clock,
        private OutputInterface $output,
        private string $command,
    ) {
    }

    public function start(): void
    {
        $this->output->writeln(sprintf(
            '<info>[%s] Starting cron action "%s"</info>',
            $this->clock->now()->format('d-m-Y H:i:s'),
            $this->cronActionId
        ));

        $process = new Process($this->command);
        $process->start();

        $process->stdout?->on('data', function (string $chunk): void {
            $this->output->write($chunk);
        });

        $process->stderr?->on('data', function (string $chunk): void {
            $this->output->write(sprintf('<error>%s</error>', $chunk));
        });

        $process->on('exit', function (): void {
            $this->output->writeln(sprintf(
                '<info>[%s] Finished cron action "%s"</info>',
                $this->clock->now()->format('d-m-Y H:i:s'),
                $this->cronActionId
            ));
        });
    }
}
