<?php

declare(strict_types=1);

namespace App\Console\Notification;

use App\Application\AppVersion;
use App\Domain\Integration\GitHub\GitHub;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\ValueObject\String\Url;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: AppUpdateAvailableNotificationConsoleCommand::NAME, description: 'Send out app update available notification', aliases: ['app:cron:app-update-available-notification'])]
final class AppUpdateAvailableNotificationConsoleCommand extends Command
{
    public const string NAME = 'app:notification:app-update-available';

    public function __construct(
        private readonly GitHub $gitHub,
        private readonly CommandBus $commandBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $latestRelease = $this->gitHub->getLatestRelease();
        if (AppVersion::getSemanticVersion() === $latestRelease) {
            return Command::SUCCESS;
        }

        $this->commandBus->dispatch(new SendNotification(
            title: 'New app version available',
            message: sprintf("We have been busy, %s is finally out! Go see what's new.", $latestRelease),
            tags: ['partying_face'],
            actionUrl: Url::fromString('https://github.com/dreeveapp/dreeve/releases'),
        ));

        return Command::SUCCESS;
    }
}
