<?php

namespace App\Tests\Console;

use App\Console\RunDaemonConsoleCommand;
use App\Tests\Infrastructure\Daemon\FakeDaemon;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use App\Tests\NullLogger;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RunDaemonConsoleCommandTest extends ConsoleCommandTestCase
{
    use MatchesSnapshots;

    private RunDaemonConsoleCommand $runDaemonConsoleCommand;

    public function testExecute(): void
    {
        $command = $this->getCommandInApplication('app:daemon:run');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $this->assertMatchesSnapshot($commandTester->getDisplay(), new ConsoleOutputSnapshotDriver());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->runDaemonConsoleCommand = new RunDaemonConsoleCommand(
            PausedClock::fromString('2025-11-08 14:47:03'),
            new FakeDaemon(),
            new NullLogger(),
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->runDaemonConsoleCommand;
    }
}
