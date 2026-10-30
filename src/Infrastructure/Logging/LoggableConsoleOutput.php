<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class LoggableConsoleOutput implements OutputInterface
{
    public function __construct(
        private OutputInterface $output,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param string[]|string $messages
     */
    private function log(iterable|string $messages): void
    {
        if (is_string($messages)) {
            $messages = [$messages];
        }

        if (!$messages = array_filter(
            $messages,
            fn (string $message): bool => !in_array($message, ['[1G', '[2K', "\r[2K", "\r [2K"])
        )) {
            return;
        }
        $this->logger->info(new Monolog(...$messages));
    }

    /**
     * @param string[]|string $messages
     */
    public function writeln(iterable|string $messages, int $options = 0): void
    {
        $this->log($messages);
        $this->output->writeln($messages, $options);
    }

    /**
     * @param string[]|string $messages
     */
    public function write(iterable|string $messages, bool $newline = false, int $options = self::OUTPUT_NORMAL): void
    {
        $this->log($messages);
        $this->output->write($messages, $newline, $options);
    }

    public function setVerbosity(int $level): void
    {
        $this->output->setVerbosity($level);
    }

    public function getVerbosity(): int
    {
        return $this->output->getVerbosity();
    }

    public function isQuiet(): bool
    {
        return $this->output->isQuiet();
    }

    public function isVerbose(): bool
    {
        return $this->output->isVerbose();
    }

    public function isVeryVerbose(): bool
    {
        return $this->output->isVeryVerbose();
    }

    public function isDebug(): bool
    {
        return $this->output->isDebug();
    }

    public function setDecorated(bool $decorated): void
    {
        $this->output->setDecorated($decorated);
    }

    public function isDecorated(): bool
    {
        return $this->output->isDecorated();
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        $this->output->setFormatter($formatter);
    }

    public function getFormatter(): OutputFormatterInterface
    {
        return $this->output->getFormatter();
    }

    public function isSilent(): bool
    {
        return $this->output->isSilent();
    }
}
