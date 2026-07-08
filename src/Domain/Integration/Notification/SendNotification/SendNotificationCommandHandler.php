<?php

declare(strict_types=1);

namespace App\Domain\Integration\Notification\SendNotification;

use App\Domain\Integration\Notification\Shoutrrr\Shoutrrr;
use App\Domain\Integration\Notification\Shoutrrr\ShoutrrrUrl;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Serialization\Json;

final readonly class SendNotificationCommandHandler implements CommandHandler
{
    public function __construct(
        private Shoutrrr $shoutrrr,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof SendNotification);

        /** @var ShoutrrrUrl $configuredNotificationUrl */
        foreach ($this->settingsRepository->integrations()->getConfiguredNotificationUrls() as $configuredNotificationUrl) {
            if (!$configuredNotificationUrl->isTelegramUrl()) {
                $configuredNotificationUrl = $configuredNotificationUrl->withParams([
                    'click' => (string) $command->getActionUrl(),
                    'icon' => 'https://raw.githubusercontent.com/dreeveapp/dreeve/master/public/assets/images/manifest/icon-192.png',
                    'tags' => implode(',', $command->getTags()),
                    'actions' => Json::encode([
                        [
                            'action' => 'view',
                            'label' => 'Open app',
                            'url' => $command->getActionUrl(),
                            'clear' => true,
                        ],
                    ]),
                ]);
            }

            $this->shoutrrr->send(
                shoutrrrUrl: $configuredNotificationUrl,
                message: $command->getMessage(),
                title: $command->getTitle(),
            );
        }
    }
}
