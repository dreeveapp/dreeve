<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Integration\AI\AIApiKey;
use App\Domain\Integration\AI\Chat\ChatCommands;
use App\Domain\Integration\AI\InvalidAIConfiguration;
use App\Domain\Integration\Notification\Shoutrrr\ConfiguredNotificationUrls;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\HuggingFace\HuggingFace;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\AzureOpenAI;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAI\Responses\OpenAIResponses;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\XAI\Grok;

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

        $aiConfig = $data['ai'] ?? [];
        $aiEnabled = filter_var($aiConfig['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($aiEnabled) {
            $providerName = empty($aiConfig['provider']) ? throw new InvalidAIConfiguration('Provider cannot be empty') : $aiConfig['provider'];
            $config = isset($aiConfig['configuration']) && [] !== $aiConfig['configuration'] ? $aiConfig['configuration'] : throw new InvalidAIConfiguration('Config cannot be empty');

            $requiredConfigKeys = match ($providerName) {
                'ollama' => ['model', 'url'],
                'azureOpenAI' => ['endpoint', 'model', 'version'],
                'openAILike' => ['baseUri', 'model'],
                default => ['model'],
            };

            if ('ollama' !== $providerName && AIApiKey::fromServerVar()->isEmpty()) {
                throw new InvalidAIConfiguration('API key cannot be empty');
            }

            foreach ($requiredConfigKeys as $key) {
                if (empty($config[$key])) {
                    throw new InvalidAIConfiguration(sprintf('%s cannot be empty', ucfirst($key)));
                }
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
            aiIntegrationWithUIEnabled: $aiEnabled && filter_var($aiConfig['enableUI'] ?? false, FILTER_VALIDATE_BOOLEAN),
            aiConfig: $aiConfig,
            chatCommands: ChatCommands::fromArray($aiConfig['agent']['commands'] ?? []),
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

    public function getAIProvider(): AIProviderInterface
    {
        $providerName = $this->aiConfig['provider'];
        $apiKey = AIApiKey::fromServerVar();

        return match ($providerName) {
            'anthropic' => new Anthropic(
                key: (string) $apiKey,
                model: $this->aiConfig['configuration']['model'],
            ),
            'azureOpenAI' => new AzureOpenAI(
                key: (string) $apiKey,
                endpoint: $this->aiConfig['configuration']['endpoint'],
                model: $this->aiConfig['configuration']['model'],
                version: $this->aiConfig['configuration']['version'],
            ),
            'deepseek' => new Deepseek(
                key: (string) $apiKey,
                model: $this->aiConfig['configuration']['model'],
            ),
            'gemini' => new Gemini(
                key: (string) $apiKey,
                model: $this->aiConfig['configuration']['model'],
            ),
            'grok' => new Grok(
                key: (string) $apiKey,
                model: $this->aiConfig['configuration']['model'],
            ),
            'huggingFace' => new HuggingFace(
                key: (string) $apiKey,
                model: $this->aiConfig['configuration']['model'],
            ),
            'ollama' => new Ollama(
                url: (string) $apiKey,
                model: $this->aiConfig['configuration']['model'],
            ),
            'openAI' => new OpenAI(
                key: (string) $apiKey,
                model: $this->aiConfig['configuration']['model'],
            ),
            'openAILike' => new OpenAILike(
                baseUri: $this->aiConfig['configuration']['baseUri'],
                key: (string) $apiKey,
                model: $this->aiConfig['configuration']['model'],
            ),
            'openAIResponses' => new OpenAIResponses(
                key: (string) $apiKey,
                model: $this->aiConfig['configuration']['model'],
            ),
            'mistral' => new Mistral(
                key: (string) $apiKey,
                model: $this->aiConfig['configuration']['model'],
            ),
            default => throw new InvalidAIConfiguration(sprintf('AI provider "%s" is not supported', $providerName)),
        };
    }
}
