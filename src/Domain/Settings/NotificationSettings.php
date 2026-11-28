<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Integration\Notification\Shoutrrr\ConfiguredNotificationUrls;

final readonly class NotificationSettings
{
    private function __construct(
        private ConfiguredNotificationUrls $configuredNotificationUrls,
        private bool $notifyOnSuccessfulImport,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $notifications = is_array($data['notifications'] ?? null) ? $data['notifications'] : [];
        $services = $notifications['services'] ?? [];
        $services = array_values(array_filter(
            is_array($services) ? $services : [],
            static fn (mixed $service): bool => is_string($service) && '' !== trim($service)
        ));

        return new self(
            configuredNotificationUrls: ConfiguredNotificationUrls::fromConfig($services),
            notifyOnSuccessfulImport: filter_var($notifications['notifyOnSuccessfulBuild'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function getConfiguredNotificationUrls(): ConfiguredNotificationUrls
    {
        return $this->configuredNotificationUrls;
    }

    public function shouldNotifyOnSuccessfulImport(): bool
    {
        return $this->notifyOnSuccessfulImport;
    }
}
