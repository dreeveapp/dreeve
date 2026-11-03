<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use Doctrine\DBAL\Driver\SQLite3\Exception;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'doctrine:migrations:migrate')]
final class LockedMigrateCommandStub extends Command
{
    public int $numberOfRuns = 0;

    public function __construct(
        private readonly int $numberOfLockedRuns,
        private readonly int $exitCode = Command::SUCCESS,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->numberOfRuns++ < $this->numberOfLockedRuns) {
            throw new LockWaitTimeoutException(new Exception('database is locked'), null);
        }

        return $this->exitCode;
    }
}
