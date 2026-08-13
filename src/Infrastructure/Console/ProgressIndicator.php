<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Infrastructure\Config\PlatformEnvironment;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressIndicator as SymfonyProgressIndicator;
use Symfony\Component\Console\Output\OutputInterface;

final class ProgressIndicator
{
    private readonly SymfonyProgressIndicator $progressIndicator;
    private ?string $startMessage = null;
    private bool $isStarted = false;

    public function __construct(OutputInterface $output)
    {
        $startTime = time();

        SymfonyProgressIndicator::setPlaceholderFormatterDefinition('indicator', static fn (): string => '');
        SymfonyProgressIndicator::setPlaceholderFormatterDefinition('elapsed', fn (): string => PlatformEnvironment::fromServer()->isTest() ? '3 s' : Helper::formatTime(time() - $startTime, 2));

        $this->progressIndicator = new SymfonyProgressIndicator(
            output: $output,
            format: 'verbose',
            finishedIndicatorValue: ''
        );
    }

    /**
     * Rendering is deferred until there is actual progress to report.
     */
    public function start(string $message): void
    {
        $this->startMessage = $message;
    }

    public function updateMessage(string $message): void
    {
        if (!$this->isStarted) {
            $this->progressIndicator->start($this->startMessage ?? $message);
            $this->isStarted = true;
        }

        $this->progressIndicator->setMessage($message);
    }

    public function finish(string $message): void
    {
        if (!$this->isStarted) {
            return;
        }

        $this->progressIndicator->finish($message);
    }
}
