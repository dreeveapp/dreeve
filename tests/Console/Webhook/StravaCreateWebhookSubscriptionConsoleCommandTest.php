<?php

namespace App\Tests\Console\Webhook;

use App\Application\AppUrl;
use App\Console\Webhook\StravaCreateWebhookSubscriptionConsoleCommand;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Strava;
use App\Tests\Console\ConsoleCommandTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class StravaCreateWebhookSubscriptionConsoleCommandTest extends ConsoleCommandTestCase
{
    use MatchesSnapshots;

    private StravaCreateWebhookSubscriptionConsoleCommand $stravaCreateWebhookSubscriptionConsoleCommand;
    private MockObject $logger;

    public function testExecute(): void
    {
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $command = $this->getCommandInApplication('app:strava:webhooks-create');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertMatchesTextSnapshot(str_replace(' ', '', $commandTester->getDisplay()));
    }

    public function testExecuteWhenNotEnabled(): void
    {
        $this->stravaCreateWebhookSubscriptionConsoleCommand = new StravaCreateWebhookSubscriptionConsoleCommand(
            $this->getContainer()->get(Strava::class),
            $this->settingsWithWebhooks(enabled: false, verifyToken: ''),
            AppUrl::fromString('https://localhost/'),
            $this->logger,
        );

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $command = $this->getCommandInApplication('app:strava:webhooks-create');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertStringContainsString(
            'Webhooks not enabled',
            $commandTester->getDisplay()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->stravaCreateWebhookSubscriptionConsoleCommand = new StravaCreateWebhookSubscriptionConsoleCommand(
            $this->getContainer()->get(Strava::class),
            $this->settingsWithWebhooks(enabled: true, verifyToken: 'el-token'),
            AppUrl::fromString('https://localhost/'),
            $this->logger = $this->createMock(LoggerInterface::class),
        );
    }

    private function settingsWithWebhooks(bool $enabled, string $verifyToken): SettingsRepository
    {
        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsGroup::IMPORT, [
            'webhooks' => ['enabled' => $enabled, 'verifyToken' => $verifyToken],
        ]);

        return $settingsRepository;
    }

    protected function getConsoleCommand(): Command
    {
        return $this->stravaCreateWebhookSubscriptionConsoleCommand;
    }
}
