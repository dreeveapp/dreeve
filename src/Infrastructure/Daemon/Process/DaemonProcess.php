<?php

declare(strict_types=1);

namespace App\Infrastructure\Daemon\Process;

use App\Infrastructure\Time\Clock\Clock;
use React\ChildProcess\Process;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @codeCoverageIgnore
 */
final readonly class DaemonProcess
{
    public function __construct(
        private DaemonProcessType $type,
        private string $name,
        private Clock $clock,
        private OutputInterface $output,
        private string $command,
    ) {
    }

    public function start(): void
    {
        $this->output->writeln(sprintf(
            '<info>[%s] Starting %s "%s"</info>',
            $this->clock->now()->format('d-m-Y H:i:s'),
            $this->type->value,
            $this->name,
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
                '<info>[%s] Finished %s "%s"</info>',
                $this->clock->now()->format('d-m-Y H:i:s'),
                $this->type->value,
                $this->name,
            ));
        });
    }
}
