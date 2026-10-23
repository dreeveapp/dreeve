<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Integration\AI\AIApiKey;
use App\Domain\Integration\AI\Chat\ChatCommands;
use App\Domain\Integration\AI\InvalidAIConfiguration;
use App\Domain\Integration\Notification\Shoutrrr\ConfiguredNotificationUrls;
use App\Infrastructure\ValueObject\String\Url;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\Factory as AnthropicFactory;
use Symfony\AI\Platform\Bridge\Anthropic\ModelCatalog as AnthropicModelCatalog;
use Symfony\AI\Platform\Bridge\Azure\OpenAi\Factory as AzureOpenAiFactory;
use Symfony\AI\Platform\Bridge\Azure\OpenAi\ModelCatalog as AzureOpenAiModelCatalog;
use Symfony\AI\Platform\Bridge\DeepSeek\DeepSeek;
use Symfony\AI\Platform\Bridge\DeepSeek\Factory as DeepSeekFactory;
use Symfony\AI\Platform\Bridge\DeepSeek\ModelCatalog as DeepSeekModelCatalog;
use Symfony\AI\Platform\Bridge\Gemini\Factory as GeminiFactory;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\ModelCatalog as GeminiModelCatalog;
use Symfony\AI\Platform\Bridge\Generic\Factory as GenericFactory;
use Symfony\AI\Platform\Bridge\Mistral\Factory as MistralFactory;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Bridge\Mistral\ModelCatalog as MistralModelCatalog;
use Symfony\AI\Platform\Bridge\Ollama\Factory as OllamaFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\ModelCatalog as OpenAiModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformInterface;

final readonly class IntegrationsSettings
{
    private const string AZURE_OPEN_AI_API_VERSION = '2024-10-21';
    private const int ANTHROPIC_MAX_TOKENS = 8192;

    private function __construct(
        private bool $aiIntegrationEnabled,
        private bool $aiIntegrationWithUIEnabled,
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
        $aiEnabled = filter_var($aiConfig['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($aiEnabled) {
            $providerName = empty($aiConfig['provider']) ? throw new InvalidAIConfiguration('Provider cannot be empty') : $aiConfig['provider'];
            $config = isset($aiConfig['configuration']) && [] !== $aiConfig['configuration'] ? $aiConfig['configuration'] : throw new InvalidAIConfiguration('Config cannot be empty');

            $requiredConfigKeys = match ($providerName) {
                'ollama' => ['model', 'url'],
                'azureOpenAI' => ['endpoint', 'model'],
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

            if ('ollama' === $providerName) {
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
            aiIntegrationWithUIEnabled: $aiEnabled && filter_var($aiConfig['enableUI'] ?? false, FILTER_VALIDATE_BOOLEAN),
            aiConfig: $aiConfig,
            chatCommands: ChatCommands::fromArray($aiConfig['agent']['commands'] ?? []),
            configuredNotificationUrls: ConfiguredNotificationUrls::fromConfig($services),
            // The stored key predates the removal of the build phase, renaming it would reset the user's setting.
            notifyOnSuccessfulImport: filter_var($notifications['notifyOnSuccessfulBuild'] ?? true, FILTER_VALIDATE_BOOLEAN),
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

    public function shouldNotifyOnSuccessfulImport(): bool
    {
        return $this->notifyOnSuccessfulImport;
    }

    /**
     * @return non-empty-string
     */
    public function getAIModel(): string
    {
        $model = $this->aiConfig['configuration']['model'];

        return match ($this->aiConfig['provider']) {
            'anthropic' => sprintf('%s?max_tokens=%d', $model, self::ANTHROPIC_MAX_TOKENS),
            default => $model,
        };
    }

    public function getAIPlatform(): PlatformInterface
    {
        $configuration = $this->aiConfig['configuration'];
        $apiKey = (string) AIApiKey::fromServerVar();
        $model = $configuration['model'];
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
        ];

        return match ($this->aiConfig['provider']) {
            'anthropic' => AnthropicFactory::createPlatform(
                apiKey: $apiKey,
                modelCatalog: new AnthropicModelCatalog([$model => ['class' => Claude::class, 'capabilities' => $capabilities]]),
            ),
            'azureOpenAI' => AzureOpenAiFactory::createPlatform(
                baseUrl: $configuration['endpoint'],
                deployment: $model,
                apiVersion: self::AZURE_OPEN_AI_API_VERSION,
                apiKey: $apiKey,
                modelCatalog: new AzureOpenAiModelCatalog([$model => ['class' => Gpt::class, 'capabilities' => $capabilities]]),
            ),
            'deepseek' => DeepSeekFactory::createPlatform(
                apiKey: $apiKey,
                modelCatalog: new DeepSeekModelCatalog([$model => ['class' => DeepSeek::class, 'capabilities' => $capabilities]]),
            ),
            'gemini' => GeminiFactory::createPlatform(
                apiKey: $apiKey,
                modelCatalog: new GeminiModelCatalog([$model => ['class' => Gemini::class, 'capabilities' => $capabilities]]),
            ),
            'grok' => GenericFactory::createPlatform(
                baseUrl: 'https://api.x.ai/v1',
                apiKey: $apiKey,
                completionsPath: '/chat/completions',
                name: 'grok',
            ),
            'huggingFace' => GenericFactory::createPlatform(
                baseUrl: 'https://router.huggingface.co/v1',
                apiKey: $apiKey,
                completionsPath: '/chat/completions',
                name: 'huggingFace',
            ),
            'ollama' => OllamaFactory::createPlatform(
                endpoint: rtrim((string) $configuration['url'], '/'),
            ),
            'openAI', 'openAIResponses' => OpenAiFactory::createPlatform(
                apiKey: $apiKey,
                modelCatalog: new OpenAiModelCatalog([$model => ['class' => Gpt::class, 'capabilities' => $capabilities]]),
            ),
            'openAILike' => GenericFactory::createPlatform(
                baseUrl: $configuration['baseUri'],
                apiKey: $apiKey,
                completionsPath: '/chat/completions',
            ),
            'mistral' => MistralFactory::createPlatform(
                apiKey: $apiKey,
                modelCatalog: new MistralModelCatalog([$model => ['class' => Mistral::class, 'capabilities' => $capabilities]]),
            ),
            default => throw new InvalidAIConfiguration(sprintf('AI provider "%s" is not supported', $this->aiConfig['provider'])),
        };
    }
}
