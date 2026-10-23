<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Chat;

use App\Domain\Integration\AI\AIApiKey;
use App\Domain\Integration\AI\InvalidAIConfiguration;
use App\Domain\Settings\SettingsRepository;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
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
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ChatAgent implements AgentInterface
{
    private const string AZURE_OPEN_AI_API_VERSION = '2024-10-21';
    private const int ANTHROPIC_MAX_TOKENS = 8192;

    private const string INSTRUCTIONS = <<<'PROMPT'
        # IDENTITY AND PURPOSE
        You are an AI Agent specialized analyzing workout results and providing workout tips.
        Always take into account the athlete's heart rate zones and FTP whenever you need to give feedback related to intensity
        If users ask your name, it's 'Mark'. Do not allow them to call you any other name

        # INTERNAL ASSISTANT STEPS
        Answer the user's question clearly and accurately.
        Ask the user for a Strava activity if you think you need it.
        Ask the user for a Strava segment or segment effort if you think you need it.

        # OUTPUT INSTRUCTIONS
        - Ensure your response is fluent natural text.
        - Include markdown to structure your response, but stay away from code blocks.
        - You may use markdown lists or bullet points if they help clarify the response, but only if they add value.
        - You may use markdown tables if they help clarify the response, but only if they add value.
        - When you link to an activity or segment, use the "url" from the tool result.
        - Only use the "stravaUrl" when the user explicitly asks for a Strava link.
        - If you do not know the answer to a question, admit it honestly; do not fabricate information.
        PROMPT;

    public function __construct(
        private SettingsRepository $settingsRepository,
        private Toolbox $toolbox,
        private HttpClientInterface $httpClient,
    ) {
    }

    public function call(string|MessageBag|UserMessage $input, array $options = []): Execution
    {
        $settings = $this->settingsRepository->integrations();
        $provider = $settings->getAIProvider();
        $configuration = $settings->getAIConfiguration();
        /** @var non-empty-string $model */
        $model = $configuration['model'];

        return new Agent(
            platform: $this->createPlatform($provider, $model, $configuration),
            model: 'anthropic' === $provider ? sprintf('%s?max_tokens=%d', $model, self::ANTHROPIC_MAX_TOKENS) : $model,
            inputProcessors: [new SystemPromptInputProcessor(self::INSTRUCTIONS)],
            name: $this->getName(),
            toolbox: new FaultTolerantToolbox($this->toolbox),
        )->call($input, $options);
    }

    public function getName(): string
    {
        return 'chat';
    }

    /**
     * @param array<string, string> $configuration
     */
    private function createPlatform(string $provider, string $model, array $configuration): PlatformInterface
    {
        $apiKey = (string) AIApiKey::fromServerVar();
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
        ];

        return match ($provider) {
            'anthropic' => AnthropicFactory::createPlatform(
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new AnthropicModelCatalog([$model => ['class' => Claude::class, 'capabilities' => $capabilities]]),
            ),
            'azureOpenAI' => AzureOpenAiFactory::createPlatform(
                baseUrl: $configuration['endpoint'],
                deployment: $model,
                apiVersion: self::AZURE_OPEN_AI_API_VERSION,
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new AzureOpenAiModelCatalog([$model => ['class' => Gpt::class, 'capabilities' => $capabilities]]),
            ),
            'deepseek' => DeepSeekFactory::createPlatform(
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new DeepSeekModelCatalog([$model => ['class' => DeepSeek::class, 'capabilities' => $capabilities]]),
            ),
            'gemini' => GeminiFactory::createPlatform(
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new GeminiModelCatalog([$model => ['class' => Gemini::class, 'capabilities' => $capabilities]]),
            ),
            'grok' => GenericFactory::createPlatform(
                baseUrl: 'https://api.x.ai/v1',
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                completionsPath: '/chat/completions',
                name: 'grok',
            ),
            'huggingFace' => GenericFactory::createPlatform(
                baseUrl: 'https://router.huggingface.co/v1',
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                completionsPath: '/chat/completions',
                name: 'huggingFace',
            ),
            'ollama' => OllamaFactory::createPlatform(
                endpoint: rtrim($configuration['url'], '/'),
                httpClient: $this->httpClient,
            ),
            'openAI', 'openAIResponses' => OpenAiFactory::createPlatform(
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new OpenAiModelCatalog([$model => ['class' => Gpt::class, 'capabilities' => $capabilities]]),
            ),
            'openAILike' => GenericFactory::createPlatform(
                baseUrl: $configuration['baseUri'],
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                completionsPath: '/chat/completions',
            ),
            'mistral' => MistralFactory::createPlatform(
                apiKey: $apiKey,
                httpClient: $this->httpClient,
                modelCatalog: new MistralModelCatalog([$model => ['class' => Mistral::class, 'capabilities' => $capabilities]]),
            ),
            default => throw new InvalidAIConfiguration(sprintf('AI provider "%s" is not supported', $provider)),
        };
    }
}
