<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Integration\AI\Chat\InvalidChatCommandsConfig;
use App\Domain\Integration\AI\InvalidAIConfiguration;
use App\Domain\Integration\Notification\Shoutrrr\ShoutrrrUrl;
use App\Domain\Settings\IntegrationsSettings;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IntegrationsSettingsTest extends TestCase
{
    private ?string $originalAIApiKey = null;

    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = IntegrationsSettings::fromArray([]);

        $this->assertFalse($settings->isAIIntegrationEnabled());
        $this->assertFalse($settings->isAIIntegrationWithUIEnabled());
        $this->assertSame([], $settings->getChatCommands()->jsonSerialize());
        $this->assertCount(0, iterator_to_array($settings->getConfiguredNotificationUrls()));
    }

    public function testItEnablesTheAIIntegration(): void
    {
        $settings = IntegrationsSettings::fromArray([
            'ai' => [
                'enabled' => true,
                'enableUI' => true,
                'provider' => 'openAI',
                'configuration' => ['key' => 'my-key', 'model' => 'cool-model'],
            ],
        ]);

        $this->assertTrue($settings->isAIIntegrationEnabled());
        $this->assertTrue($settings->isAIIntegrationWithUIEnabled());
    }

    public function testItEnablesTheAIIntegrationWithoutUI(): void
    {
        $settings = IntegrationsSettings::fromArray([
            'ai' => [
                'enabled' => true,
                'enableUI' => false,
                'provider' => 'openAI',
                'configuration' => ['key' => 'my-key', 'model' => 'cool-model'],
            ],
        ]);

        $this->assertTrue($settings->isAIIntegrationEnabled());
        $this->assertFalse($settings->isAIIntegrationWithUIEnabled());
    }

    #[DataProvider(methodName: 'provideInvalidAIConfig')]
    public function testItGuardsValidAIConfiguration(array $config, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(new InvalidAIConfiguration($expectedExceptionMessage));

        IntegrationsSettings::fromArray(['ai' => [
            'enabled' => true,
            ...$config,
        ]]);
    }

    public function testItGuardsFilledOutAIApiKey(): void
    {
        $_SERVER['AI_API_KEY'] = '';
        $this->expectExceptionObject(new InvalidAIConfiguration('API key cannot be empty'));

        IntegrationsSettings::fromArray([
            'ai' => [
                'enabled' => true,
                'enableUI' => false,
                'provider' => 'openAI',
                'configuration' => ['model' => 'cool-model'],
            ],
        ]);
    }

    #[DataProvider(methodName: 'provideGetAIProviderConfig')]
    public function testGetAIProvider(array $config, AIProviderInterface $expectedProvider): void
    {
        $settings = IntegrationsSettings::fromArray(['ai' => [
            'enabled' => true,
            ...$config,
        ]]);
        $this->assertEquals(
            $expectedProvider::class,
            $settings->getAIProvider()::class
        );
    }

    public function testItBuildsChatCommands(): void
    {
        $settings = IntegrationsSettings::fromArray([
            'ai' => [
                'agent' => [
                    'commands' => [
                        ['command' => 'ftp', 'message' => 'What is my FTP?'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            ['/ftp' => 'What is my FTP?'],
            $settings->getChatCommands()->jsonSerialize(),
        );
    }

    public function testItBuildsConfiguredNotificationUrls(): void
    {
        $settings = IntegrationsSettings::fromArray([
            'notifications' => [
                'services' => [
                    'ntfy://admin:pass@ntfy.sh/el-test',
                    'discord://token@webhookid?thread_id=123456789',
                    '',
                ],
            ],
        ]);

        /** @var ShoutrrrUrl[] $urls */
        $urls = iterator_to_array($settings->getConfiguredNotificationUrls());
        $this->assertCount(2, $urls);
    }

    public function testItThrowsForAnInvalidChatCommand(): void
    {
        $this->expectExceptionObject(new InvalidChatCommandsConfig('commands must not start with a slash. (/ftp)'));

        IntegrationsSettings::fromArray([
            'ai' => [
                'agent' => [
                    'commands' => [
                        ['command' => '/ftp', 'message' => 'What is my FTP?'],
                    ],
                ],
            ],
        ]);
    }

    public static function provideGetAIProviderConfig(): iterable
    {
        yield 'anthropic' => [
            [
                'provider' => 'anthropic',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            new Anthropic('key', 'model'),
        ];

        yield 'azureOpenAI' => [
            [
                'provider' => 'azureOpenAI',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                    'endpoint' => 'endpoint',
                    'version' => 'version',
                ],
            ],
            new AzureOpenAI('key', 'endpoint', 'model', 'version'),
        ];

        yield 'deepseek' => [
            [
                'provider' => 'deepseek',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            new Deepseek('key', 'model'),
        ];

        yield 'gemini' => [
            [
                'provider' => 'gemini',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            new Gemini('key', 'model'),
        ];

        yield 'grok' => [
            [
                'provider' => 'grok',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            new Grok('key', 'model'),
        ];

        yield 'huggingFace' => [
            [
                'provider' => 'huggingFace',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            new HuggingFace('key', 'model'),
        ];

        yield 'ollama' => [
            [
                'provider' => 'ollama',
                'configuration' => [
                    'url' => 'url',
                    'model' => 'model',
                ],
            ],
            new Ollama('key', 'model'),
        ];

        yield 'openAI' => [
            [
                'provider' => 'openAI',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            new OpenAI('key', 'model'),
        ];

        yield 'openAILike' => [
            [
                'provider' => 'openAILike',
                'configuration' => [
                    'baseUri' => 'baseUri',
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            new OpenAILike('baseUri', 'key', 'model'),
        ];

        yield 'openAIResponses' => [
            [
                'provider' => 'openAIResponses',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            new OpenAIResponses('key', 'model'),
        ];

        yield 'mistral' => [
            [
                'provider' => 'mistral',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            new Mistral('key', 'model'),
        ];
    }

    public static function provideInvalidAIConfig(): iterable
    {
        yield 'Empty provider name' => [
            [],
            'Provider cannot be empty',
        ];

        yield 'Empty provider config' => [
            [
                'provider' => 'anthropic',
            ],
            'Config cannot be empty',
        ];

        yield 'Invalid configuration missing model' => [
            [
                'provider' => 'anthropic',
                'configuration' => [
                    'key' => 'lol',
                ],
            ],
            'Model cannot be empty',
        ];

        yield 'Invalid configuration missing url' => [
            [
                'provider' => 'ollama',
                'configuration' => [
                    'model' => 'lol',
                ],
            ],
            'Url cannot be empty',
        ];

        yield 'Invalid configuration missing baseUri' => [
            [
                'provider' => 'openAILike',
                'configuration' => [
                    'model' => 'lol',
                    'key' => 'key',
                ],
            ],
            'BaseUri cannot be empty',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalAIApiKey = $_SERVER['AI_API_KEY'];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $_SERVER['AI_API_KEY'] = $this->originalAIApiKey;
    }
}
