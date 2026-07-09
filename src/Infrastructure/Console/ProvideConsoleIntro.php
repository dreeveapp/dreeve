<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\AppName;
use App\Application\AppVersion;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Kernel;

trait ProvideConsoleIntro
{
    public function outputConsoleIntro(SymfonyStyle $output): void
    {
        $output->block(
            messages: [
                sprintf('%s %s', AppName::LABEL, AppVersion::getSemanticVersion()),
            ],
            style: 'fg=black;bg=green',
            padding: true
        );
        $this->outputRuntimeAndConfig($output);
    }

    public function outputDaemonConsoleIntro(SymfonyStyle $output, Clock $clock): void
    {
        $output->block(
            messages: [
                sprintf('%s %s | DAEMON', AppName::LABEL, AppVersion::getSemanticVersion()),
                sprintf('Started on %s', $clock->getCurrentDateTimeImmutable()->format('d-m-Y H:i:s')),
            ],
            style: 'fg=black;bg=green',
            padding: true
        );
        $this->outputRuntimeAndConfig($output);
    }

    private function outputRuntimeAndConfig(SymfonyStyle $output): void
    {
        $output->writeln(str_repeat('-', 20));
        $output->newLine();
        $output->text(sprintf('Runtime: PHP %s (%s) - Symfony %s - %s', PHP_VERSION, PHP_SAPI, Kernel::VERSION, PHP_OS));
        $output->newLine();
        $output->text(sprintf('Configured import mode: %s', ImportMode::fromServerVar()->value));
        $output->newLine();
        $output->writeln(str_repeat('-', 20));
        $output->newLine();
    }
}
