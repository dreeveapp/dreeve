<?php

namespace App\Tests\Console;

use App\Console\RunDaemonConsoleCommand;
use App\Tests\Infrastructure\Daemon\FakeDaemon;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RunDaemonConsoleCommandTest extends ConsoleCommandTestCase
{
    private RunDaemonConsoleCommand $runDaemonConsoleCommand;

    public function testExecute(): void
    {
        $command = $this->getCommandInApplication('app:daemon:run');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('| DAEMON', $display);
        $this->assertStringContainsString('Started on 08-11-2025 14:47:03', $display);
        $this->assertStringContainsString('Configured import mode: stravaApi', $display);
        $this->assertStringEndsWith("Cron configured\nPeriodic timer added\n", $display);
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
