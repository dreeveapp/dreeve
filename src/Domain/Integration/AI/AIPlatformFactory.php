<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI;

use App\Domain\Settings\SettingsRepository;
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
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AIPlatformFactory
{
    private const string AZURE_OPEN_AI_API_VERSION = '2024-10-21';
    private const int ANTHROPIC_MAX_TOKENS = 8192;

    public function __construct(
        private SettingsRepository $settingsRepository,
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function getModel(): string
    {
        $settings = $this->settingsRepository->integrations();
        /** @var non-empty-string $model */
        $model = $settings->getAIConfiguration()['model'];

        return AIProvider::ANTHROPIC === $settings->getAIProvider() ? sprintf('%s?max_tokens=%d', $model, self::ANTHROPIC_MAX_TOKENS) : $model;
    }

    /**
     * @param list<Capability> $capabilities
     */
    public function createPlatform(array $capabilities): PlatformInterface
    {
        $settings = $this->settingsRepository->integrations();
        $configuration = $settings->getAIConfiguration();
        $model = $configuration['model'];
        $apiKey = (string) AIApiKey::fromServerVar();

        return match ($settings->getAIProvider()) {
            AIProvider::ANTHROPIC => AnthropicFactory::createPlatform(
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new AnthropicModelCatalog([$model => ['class' => Claude::class, 'capabilities' => $capabilities]]),
            ),
            AIProvider::AZURE_OPEN_AI => AzureOpenAiFactory::createPlatform(
                baseUrl: $configuration['endpoint'],
                deployment: $model,
                apiVersion: self::AZURE_OPEN_AI_API_VERSION,
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new AzureOpenAiModelCatalog([$model => ['class' => Gpt::class, 'capabilities' => $capabilities]]),
            ),
            AIProvider::DEEPSEEK => DeepSeekFactory::createPlatform(
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new DeepSeekModelCatalog([$model => ['class' => DeepSeek::class, 'capabilities' => $capabilities]]),
            ),
            AIProvider::GEMINI => GeminiFactory::createPlatform(
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new GeminiModelCatalog([$model => ['class' => Gemini::class, 'capabilities' => $capabilities]]),
            ),
            AIProvider::GROK => GenericFactory::createPlatform(
                baseUrl: 'https://api.x.ai/v1',
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                completionsPath: '/chat/completions',
                name: 'grok',
            ),
            AIProvider::HUGGING_FACE => GenericFactory::createPlatform(
                baseUrl: 'https://router.huggingface.co/v1',
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                completionsPath: '/chat/completions',
                name: 'huggingFace',
            ),
            AIProvider::OLLAMA => OllamaFactory::createPlatform(
                endpoint: rtrim($configuration['url'], '/'),
                httpClient: $this->httpClient,
            ),
            AIProvider::OPEN_AI, AIProvider::OPEN_AI_RESPONSES => OpenAiFactory::createPlatform(
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new OpenAiModelCatalog([$model => ['class' => Gpt::class, 'capabilities' => $capabilities]]),
            ),
            AIProvider::OPEN_AI_LIKE => GenericFactory::createPlatform(
                baseUrl: $configuration['baseUri'],
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                completionsPath: '/chat/completions',
            ),
            AIProvider::MISTRAL => MistralFactory::createPlatform(
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new MistralModelCatalog([$model => ['class' => Mistral::class, 'capabilities' => $capabilities]]),
            ),
        };
    }
}
