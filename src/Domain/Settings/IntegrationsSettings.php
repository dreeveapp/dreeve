<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Integration\AI\AIProviderFactory;
use App\Domain\Integration\AI\Chat\ChatCommands;
use App\Domain\Integration\Notification\Shoutrrr\ConfiguredNotificationUrls;
use NeuronAI\Providers\AIProviderInterface;

final readonly class IntegrationsSettings
{
    private function __construct(
        private bool $aiIntegrationEnabled,
        private bool $aiIntegrationWithUIEnabled,
        /** @var array<string, mixed> */
        #[\SensitiveParameter]
        private array $aiConfig,
        private ChatCommands $chatCommands,
        private ConfiguredNotificationUrls $configuredNotificationUrls,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $ai = is_array($data['ai'] ?? null) ? $data['ai'] : [];
        $aiEnabled = filter_var($ai['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $aiUIEnabled = $aiEnabled && filter_var($ai['enableUI'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $commands = $ai['agent']['commands'] ?? [];
        $commands = is_array($commands) ? array_values($commands) : [];

        $notifications = is_array($data['notifications'] ?? null) ? $data['notifications'] : [];
        $services = $notifications['services'] ?? [];
        $services = array_values(array_filter(
            is_array($services) ? $services : [],
            static fn (mixed $service): bool => is_string($service) && '' !== trim($service)
        ));

        return new self(
            aiIntegrationEnabled: $aiEnabled,
            aiIntegrationWithUIEnabled: $aiUIEnabled,
            aiConfig: $ai,
            chatCommands: ChatCommands::fromArray($commands),
            configuredNotificationUrls: ConfiguredNotificationUrls::fromConfig($services),
        );
    }

    public function isAIIntegrationEnabled(): bool
    {
        return $this->aiIntegrationEnabled;
    }

    public function isAIIntegrationWithUIEnabled(): bool
    {
        return $this->aiIntegrationWithUIEnabled;
    }

    public function getChatCommands(): ChatCommands
    {
        return $this->chatCommands;
    }

    public function getConfiguredNotificationUrls(): ConfiguredNotificationUrls
    {
        return $this->configuredNotificationUrls;
    }

    public function createAIProvider(): AIProviderInterface
    {
        return new AIProviderFactory($this->aiConfig)->create();
    }
}
