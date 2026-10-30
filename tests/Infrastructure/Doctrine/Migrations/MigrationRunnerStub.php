<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use App\Infrastructure\Doctrine\Migrations\MigrationRunner;
use Symfony\Component\Console\Output\OutputInterface;

final class MigrationRunnerStub implements MigrationRunner
{
    private int $numberOfChecks = 0;

    public function __construct(
        private readonly ?\Throwable $runException = null,
        private readonly ?\Throwable $firstCheckException = null,
        private readonly int $numberOfChecksBeforeLatestVersion = 0,
    ) {
    }

    public function run(OutputInterface $output): void
    {
        if ($this->runException) {
            throw $this->runException;
        }
    }

    public function isAtLatestVersion(): bool
    {
        if (0 === $this->numberOfChecks++ && $this->firstCheckException) {
            throw $this->firstCheckException;
        }

        return $this->numberOfChecks > $this->numberOfChecksBeforeLatestVersion;
    }
}
