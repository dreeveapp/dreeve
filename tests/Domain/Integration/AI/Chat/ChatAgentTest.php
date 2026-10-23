<?php

declare(strict_types=1);

namespace App\Tests\Domain\Integration\AI\Chat;

use App\Domain\Integration\AI\Chat\ChatAgent;
use App\Domain\Integration\AI\Chat\Toolbox;
use App\Domain\Integration\AI\InvalidAIConfiguration;
use App\Domain\Settings\IntegrationsSettings;
use App\Domain\Settings\SettingsRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ChatAgentTest extends TestCase
{
    private ?string $originalAIApiKey = null;

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $expectedRequest
     */
    #[DataProvider(methodName: 'provideProviders')]
    public function testItCallsTheConfiguredProvider(array $config, array $expectedRequest): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            if (str_ends_with($url, '/api/show')) {
                return new MockResponse('{"capabilities":["completion","tools"]}');
            }

            $body = json_decode($options['body'], true);
            $requests[] = array_filter([
                'url' => $url,
                'model' => $body['model'] ?? null,
                'max_tokens' => $body['max_tokens'] ?? null,
            ]);

            return new MockResponse('{}', ['http_code' => 500]);
        });

        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository->method('integrations')->willReturn(IntegrationsSettings::fromArray(['ai' => [
            'enabled' => true,
            ...$config,
        ]]));

        try {
            new ChatAgent($settingsRepository, new Toolbox([]), $httpClient)->call('Hi')->getResult();
        } catch (ExceptionInterface) {
        }

        $this->assertSame([$expectedRequest], $requests);
    }

    public function testItThrowsForAnUnsupportedProvider(): void
    {
        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository->method('integrations')->willReturn(IntegrationsSettings::fromArray(['ai' => [
            'enabled' => true,
            'provider' => 'unknown',
            'configuration' => ['model' => 'model'],
        ]]));

        $this->expectExceptionObject(new InvalidAIConfiguration('AI provider "unknown" is not supported'));

        new ChatAgent($settingsRepository, new Toolbox([]), new MockHttpClient())->call('Hi');
    }

    public static function provideProviders(): iterable
    {
        yield 'anthropic' => [
            ['provider' => 'anthropic', 'configuration' => ['model' => 'model']],
            ['url' => 'https://api.anthropic.com/v1/messages', 'model' => 'model', 'max_tokens' => 8192],
        ];
        yield 'azureOpenAI' => [
            ['provider' => 'azureOpenAI', 'configuration' => ['model' => 'model', 'endpoint' => 'https://my-resource.openai.azure.com/']],
            ['url' => 'https://my-resource.openai.azure.com/openai/v1/responses', 'model' => 'model'],
        ];
        yield 'deepseek' => [
            ['provider' => 'deepseek', 'configuration' => ['model' => 'model']],
            ['url' => 'https://api.deepseek.com/chat/completions', 'model' => 'model'],
        ];
        yield 'gemini' => [
            ['provider' => 'gemini', 'configuration' => ['model' => 'model']],
            ['url' => 'https://generativelanguage.googleapis.com/v1beta/models/model:generateContent'],
        ];
        yield 'grok' => [
            ['provider' => 'grok', 'configuration' => ['model' => 'model']],
            ['url' => 'https://api.x.ai/v1/chat/completions', 'model' => 'model'],
        ];
        yield 'huggingFace' => [
            ['provider' => 'huggingFace', 'configuration' => ['model' => 'model']],
            ['url' => 'https://router.huggingface.co/v1/chat/completions', 'model' => 'model'],
        ];
        yield 'ollama' => [
            ['provider' => 'ollama', 'configuration' => ['model' => 'model', 'url' => 'http://dreeve-ollama:11434/api/']],
            ['url' => 'http://dreeve-ollama:11434/api/chat', 'model' => 'model'],
        ];
        yield 'openAI' => [
            ['provider' => 'openAI', 'configuration' => ['model' => 'model']],
            ['url' => 'https://api.openai.com/v1/responses', 'model' => 'model'],
        ];
        yield 'openAILike' => [
            ['provider' => 'openAILike', 'configuration' => ['model' => 'model', 'baseUri' => 'https://openrouter.ai/api/v1']],
            ['url' => 'https://openrouter.ai/api/v1/chat/completions', 'model' => 'model'],
        ];
        yield 'openAIResponses' => [
            ['provider' => 'openAIResponses', 'configuration' => ['model' => 'model']],
            ['url' => 'https://api.openai.com/v1/responses', 'model' => 'model'],
        ];
        yield 'mistral' => [
            ['provider' => 'mistral', 'configuration' => ['model' => 'model']],
            ['url' => 'https://api.mistral.ai/v1/chat/completions', 'model' => 'model'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalAIApiKey = $_SERVER['AI_API_KEY'];
        $_SERVER['AI_API_KEY'] = 'sk-key';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $_SERVER['AI_API_KEY'] = $this->originalAIApiKey;
    }
}
