<?php

namespace App\Tests\Console\Strava;

use App\Console\Strava\StravaDeleteWebhookSubscriptionConsoleCommand;
use App\Domain\Strava\Strava;
use App\Tests\Console\ConsoleCommandTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class StravaDeleteWebhookSubscriptionConsoleCommandTest extends ConsoleCommandTestCase
{
    private StravaDeleteWebhookSubscriptionConsoleCommand $stravaDeleteWebhookSubscriptionConsoleCommand;
    private MockObject $logger;

    public function testExecute(): void
    {
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $command = $this->getCommandInApplication('app:strava:webhooks-delete');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['y']);

        $commandTester->execute([
            'command' => $command->getName(),
            'subscriptionId' => '123',
        ]);

        $display = (string) preg_replace('/\s+/', ' ', $commandTester->getDisplay());
        $this->assertStringContainsString('Are you sure you want to delete subscription with ID 123?', $display);
        $this->assertStringContainsString('[OK] Webhook subscription deleted successfully!', $display);
    }

    public function testExecuteWithAbortion(): void
    {
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $command = $this->getCommandInApplication('app:strava:webhooks-delete');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['n']);

        $commandTester->execute([
            'command' => $command->getName(),
            'subscriptionId' => '123',
        ]);

        $display = (string) preg_replace('/\s+/', ' ', $commandTester->getDisplay());
        $this->assertStringContainsString('Are you sure you want to delete subscription with ID 123?', $display);
        $this->assertStringNotContainsString('[OK]', $display);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->stravaDeleteWebhookSubscriptionConsoleCommand = new StravaDeleteWebhookSubscriptionConsoleCommand(
            $this->getContainer()->get(Strava::class),
            $this->logger = $this->createMock(LoggerInterface::class),
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->stravaDeleteWebhookSubscriptionConsoleCommand;
    }
}
