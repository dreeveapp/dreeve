<?php

declare(strict_types=1);

namespace App\Domain\Integration\Notification\SendNotification;

use App\Domain\Integration\Notification\Shoutrrr\ConfiguredNotificationServices;
use App\Domain\Integration\Notification\Shoutrrr\Shoutrrr;
use App\Domain\Integration\Notification\Shoutrrr\ShoutrrrUrl;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Serialization\Json;
use Stringable;

final readonly class SendNotificationCommandHandler implements CommandHandler
{
    public function __construct(
        private Shoutrrr $shoutrrr,
        private ConfiguredNotificationServices $configuredNotificationServices,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof SendNotification);

        /** @var ShoutrrrUrl $configuredNotificationService */
        foreach ($this->configuredNotificationServices as $configuredNotificationService) {
            $shoutrrrUrl = $configuredNotificationService;

            if (!$this->isTelegramUrl($configuredNotificationService)) {
                $shoutrrrUrl = $configuredNotificationService->withParams([
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
                shoutrrrUrl: $shoutrrrUrl,
                message: $command->getMessage(),
                title: $command->getTitle(),
            );
        }
    }

    private function isTelegramUrl(Stringable|string $shoutrrrUrl): bool
    {
        return str_starts_with((string) $shoutrrrUrl, 'telegram://');
    }
}
