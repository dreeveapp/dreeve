<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Integration\AI\Chat\InvalidChatCommandsConfig;
use App\Domain\Integration\AI\InvalidAIConfiguration;
use App\Domain\Integration\Notification\Shoutrrr\ShoutrrrUrl;
use App\Domain\Settings\IntegrationsSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\DeepSeek\DeepSeek;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Platform;

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
        $this->assertTrue($settings->shouldNotifyOnSuccessfulImport());
    }

    public function testItDisablesTheSuccessfulBuildNotification(): void
    {
        $settings = IntegrationsSettings::fromArray([
            'notifications' => ['notifyOnSuccessfulBuild' => '0'],
        ]);

        $this->assertFalse($settings->shouldNotifyOnSuccessfulImport());
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

    #[DataProvider(methodName: 'provideGetAIPlatformConfig')]
    public function testGetAIPlatform(array $config, string $expectedModelClass, ?int $expectedMaxTokens = null): void
    {
        $_SERVER['AI_API_KEY'] = 'sk-key';

        $settings = IntegrationsSettings::fromArray(['ai' => [
            'enabled' => true,
            ...$config,
        ]]);

        $model = $settings->getAIPlatform()->getModelCatalog()->getModel($settings->getAIModel());

        $this->assertInstanceOf($expectedModelClass, $model);
        $this->assertSame('model', $model->getName());
        $this->assertSame($expectedMaxTokens, $model->getOptions()['max_tokens'] ?? null);
    }

    public function testItGuardsAValidOllamaUrl(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid url "dreeve-ollama:11434/api"'));

        IntegrationsSettings::fromArray(['ai' => [
            'enabled' => true,
            'provider' => 'ollama',
            'configuration' => [
                'url' => 'dreeve-ollama:11434/api',
                'model' => 'model',
            ],
        ]]);
    }

    public function testGetAIPlatformForOllama(): void
    {
        $settings = IntegrationsSettings::fromArray(['ai' => [
            'enabled' => true,
            'provider' => 'ollama',
            'configuration' => [
                'url' => 'http://dreeve-ollama:11434/api',
                'model' => 'model',
            ],
        ]]);

        $this->assertInstanceOf(Platform::class, $settings->getAIPlatform());
        $this->assertSame('model', $settings->getAIModel());
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

    public static function provideGetAIPlatformConfig(): iterable
    {
        yield 'anthropic' => [
            [
                'provider' => 'anthropic',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            Claude::class,
            8192,
        ];

        yield 'azureOpenAI' => [
            [
                'provider' => 'azureOpenAI',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                    'endpoint' => 'endpoint',
                ],
            ],
            Gpt::class,
        ];

        yield 'deepseek' => [
            [
                'provider' => 'deepseek',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            DeepSeek::class,
        ];

        yield 'gemini' => [
            [
                'provider' => 'gemini',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            Gemini::class,
        ];

        yield 'grok' => [
            [
                'provider' => 'grok',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            CompletionsModel::class,
        ];

        yield 'huggingFace' => [
            [
                'provider' => 'huggingFace',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            CompletionsModel::class,
        ];

        yield 'openAI' => [
            [
                'provider' => 'openAI',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            Gpt::class,
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
            CompletionsModel::class,
        ];

        yield 'openAIResponses' => [
            [
                'provider' => 'openAIResponses',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            Gpt::class,
        ];

        yield 'mistral' => [
            [
                'provider' => 'mistral',
                'configuration' => [
                    'key' => 'key',
                    'model' => 'model',
                ],
            ],
            Mistral::class,
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
