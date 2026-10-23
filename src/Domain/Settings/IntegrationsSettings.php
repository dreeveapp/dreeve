<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Integration\AI\AIApiKey;
use App\Domain\Integration\AI\AIProvider;
use App\Domain\Integration\AI\Chat\ChatCommands;
use App\Domain\Integration\AI\InvalidAIConfiguration;
use App\Domain\Integration\Notification\Shoutrrr\ConfiguredNotificationUrls;
use App\Infrastructure\ValueObject\String\Url;

final readonly class IntegrationsSettings
{
    private function __construct(
        private bool $aiIntegrationEnabled,
        /** @var array<string, mixed> */
        #[\SensitiveParameter]
        private array $aiConfig,
        private ChatCommands $chatCommands,
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

        $aiConfig = $data['ai'] ?? [];
        $aiEnabled = filter_var($aiConfig['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && filter_var($aiConfig['enableUI'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if ($aiEnabled) {
            $providerName = empty($aiConfig['provider']) ? throw new InvalidAIConfiguration('Provider cannot be empty') : $aiConfig['provider'];
            $provider = AIProvider::tryFrom($providerName) ?? throw new InvalidAIConfiguration(sprintf('AI provider "%s" is not supported', $providerName));
            $config = isset($aiConfig['configuration']) && [] !== $aiConfig['configuration'] ? $aiConfig['configuration'] : throw new InvalidAIConfiguration('Config cannot be empty');

            if ($provider->requiresApiKey() && AIApiKey::fromServerVar()->isEmpty()) {
                throw new InvalidAIConfiguration('API key cannot be empty');
            }

            foreach ($provider->getRequiredConfigKeys() as $key) {
                if (empty($config[$key])) {
                    throw new InvalidAIConfiguration(sprintf('%s cannot be empty', ucfirst($key)));
                }
            }

            if (AIProvider::OLLAMA === $provider) {
                Url::fromString($config['url']);
            }
        }

        $notifications = is_array($data['notifications'] ?? null) ? $data['notifications'] : [];
        $services = $notifications['services'] ?? [];
        $services = array_values(array_filter(
            is_array($services) ? $services : [],
            static fn (mixed $service): bool => is_string($service) && '' !== trim($service)
        ));

        return new self(
            aiIntegrationEnabled: $aiEnabled,
            aiConfig: $aiConfig,
            chatCommands: ChatCommands::fromArray($aiConfig['agent']['commands'] ?? []),
            configuredNotificationUrls: ConfiguredNotificationUrls::fromConfig($services),
            notifyOnSuccessfulImport: filter_var($notifications['notifyOnSuccessfulBuild'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function isAIIntegrationEnabled(): bool
    {
        return $this->aiIntegrationEnabled;
    }

    public function getChatCommands(): ChatCommands
    {
        return $this->chatCommands;
    }

    public function getConfiguredNotificationUrls(): ConfiguredNotificationUrls
    {
        return $this->configuredNotificationUrls;
    }

    public function shouldNotifyOnSuccessfulImport(): bool
    {
        return $this->notifyOnSuccessfulImport;
    }

    public function getAIProvider(): AIProvider
    {
        return AIProvider::from($this->aiConfig['provider']);
    }

    /**
     * @return array<string, string>
     */
    public function getAIConfiguration(): array
    {
        return $this->aiConfig['configuration'];
    }
}
