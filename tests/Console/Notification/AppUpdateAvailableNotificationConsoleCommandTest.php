<?php

namespace App\Tests\Console\Notification;

use App\Application\AppVersion;
use App\Console\Notification\AppUpdateAvailableNotificationConsoleCommand;
use App\Domain\Integration\GitHub\GitHub;
use App\Infrastructure\Serialization\Json;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class AppUpdateAvailableNotificationConsoleCommandTest extends ConsoleCommandTestCase
{
    use MatchesSnapshots;

    private AppUpdateAvailableNotificationConsoleCommand $command;
    private SpyCommandBus $commandBus;
    /**
     * @var MockObject&Client
     */
    private MockObject $client;

    public function testExecute(): void
    {
        $this->client
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.github.com/repos/dreeveapp/dreeve/releases/latest')
            ->willReturn(new Response(status: 200, body: Json::encode(['name' => 'v3.8.0'])));

        $command = $this->getCommandInApplication('app:notification:app-update-available');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testExecuteWhenSameVersions(): void
    {
        $this->client
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.github.com/repos/dreeveapp/dreeve/releases/latest')
            ->willReturn(new Response(status: 200, body: Json::encode(['name' => AppVersion::getSemanticVersion()])));

        $command = $this->getCommandInApplication('app:notification:app-update-available');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($this->commandBus->getDispatchedCommands());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(Client::class);

        $this->command = new AppUpdateAvailableNotificationConsoleCommand(
            new GitHub($this->client),
            $this->commandBus = new SpyCommandBus(),
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->command;
    }
}
