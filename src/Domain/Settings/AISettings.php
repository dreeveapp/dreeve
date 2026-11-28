<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Integration\AI\AIApiKey;
use App\Domain\Integration\AI\AIProvider;
use App\Domain\Integration\AI\Chat\ChatCommands;
use App\Domain\Integration\AI\InvalidAIConfiguration;
use App\Infrastructure\ValueObject\String\Url;

final readonly class AISettings
{
    private function __construct(
        private bool $aiChatEnabled,
        private bool $aiMediaLocationSuggestionEnabled,
        /** @var array<string, mixed> */
        #[\SensitiveParameter]
        private array $aiConfig,
        private ChatCommands $chatCommands,
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

        return new self(
            aiChatEnabled: $aiEnabled && filter_var($aiConfig['agent']['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            aiMediaLocationSuggestionEnabled: $aiEnabled && filter_var($aiConfig['mediaLocation']['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            aiConfig: $aiConfig,
            chatCommands: ChatCommands::fromArray($aiConfig['agent']['commands'] ?? []),
        );
    }

    public function isAIChatEnabled(): bool
    {
        return $this->aiChatEnabled;
    }

    public function isAIMediaLocationSuggestionEnabled(): bool
    {
        return $this->aiMediaLocationSuggestionEnabled;
    }

    public function getChatCommands(): ChatCommands
    {
        return $this->chatCommands;
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
