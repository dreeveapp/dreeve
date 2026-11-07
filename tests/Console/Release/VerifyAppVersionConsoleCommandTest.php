<?php

declare(strict_types=1);

namespace App\Tests\Console\Release;

use App\Application\AppVersion;
use App\Console\Release\VerifyAppVersionConsoleCommand;
use App\Tests\Console\ConsoleCommandTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class VerifyAppVersionConsoleCommandTest extends ConsoleCommandTestCase
{
    private VerifyAppVersionConsoleCommand $command;

    public function testExecute(): void
    {
        $command = $this->getCommandInApplication('app:release:verify-version');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'tag' => AppVersion::getSemanticVersion(),
        ]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            sprintf('AppVersion %s matches the tag', AppVersion::getSemanticVersion()),
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteWhenTagDoesNotMatch(): void
    {
        $command = $this->getCommandInApplication('app:release:verify-version');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'tag' => 'v0.0.0',
        ]);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            sprintf('AppVersion is %s but the tag is v0.0.0', AppVersion::getSemanticVersion()),
            $commandTester->getDisplay(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new VerifyAppVersionConsoleCommand();
    }

    protected function getConsoleCommand(): Command
    {
        return $this->command;
    }
}
