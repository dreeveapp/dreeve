<?php

declare(strict_types=1);

namespace App\Application\Import\SendImportSuccessfulNotification;

use App\Application\AppUrl;
use App\Domain\Activity\Activity;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SendImportSuccessfulNotificationCommandHandler implements CommandHandler
{
    public function __construct(
        private CommandBus $commandBus,
        private SettingsRepository $settingsRepository,
        private AppUrl $appUrl,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof SendImportSuccessfulNotification);

        if (!$this->settingsRepository->integrations()->shouldNotifyOnSuccessfulImport()) {
            return;
        }

        $importedActivities = $command->getImportedActivities();
        $numberOfImportedActivities = count($importedActivities);

        if (0 === $numberOfImportedActivities) {
            $this->commandBus->dispatch(new SendNotification(
                title: 'Import successful',
                message: 'New import of your stats was successful',
                tags: ['+1'],
                actionUrl: $this->appUrl,
            ));

            return;
        }

        if (1 === $numberOfImportedActivities) {
            /** @var Activity $activity */
            $activity = $importedActivities->getFirst();

            $this->commandBus->dispatch(new SendNotification(
                title: 'Import successful',
                message: '1 new activity was imported',
                tags: ['+1'],
                actionUrl: $this->appUrl->withPath($this->urlGenerator->generate('activity', ['activityId' => (string) $activity->getId()])),
                actionLabel: 'Open activity',
            ));

            return;
        }

        $this->commandBus->dispatch(new SendNotification(
            title: 'Import successful',
            message: sprintf('%d new activities were imported', $numberOfImportedActivities),
            tags: ['+1'],
            actionUrl: $this->appUrl->withPath($this->urlGenerator->generate('activities')),
            actionLabel: 'Open activities',
        ));
    }
}
