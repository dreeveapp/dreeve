<?php

namespace App\Tests\Console\Strava;

use App\Console\Strava\StravaViewWebhookSubscriptionConsoleCommand;
use App\Domain\Strava\Strava;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\SpyOutput;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Tester\CommandTester;

class StravaViewWebhookSubscriptionConsoleCommandTest extends ConsoleCommandTestCase
{
    use MatchesSnapshots;

    private StravaViewWebhookSubscriptionConsoleCommand $stravaViewWebhookSubscriptionConsoleCommand;
    private MockObject $logger;

    public function testExecute(): void
    {
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $command = $this->getCommandInApplication('app:strava:webhooks-view');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertMatchesTextSnapshot(str_replace(' ', '', $commandTester->getDisplay()));
    }

    public function testExecuteWhenNoSubscriptions(): void
    {
        $strava = $this->createMock(Strava::class);
        $strava
            ->expects($this->once())
            ->method('getWebhookSubscription')
            ->willReturn([]);

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $output = new SpyOutput();
        $command = new StravaViewWebhookSubscriptionConsoleCommand(
            $strava,
            $this->logger,
        );
        $command->run($this->createStub(Input::class), $output);

        $this->assertStringContainsString('No webhook subscriptions found', $output);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->stravaViewWebhookSubscriptionConsoleCommand = new StravaViewWebhookSubscriptionConsoleCommand(
            $this->getContainer()->get(Strava::class),
            $this->logger = $this->createMock(LoggerInterface::class),
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->stravaViewWebhookSubscriptionConsoleCommand;
    }
}
