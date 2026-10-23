<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Integration\AI\AIProvider;
use App\Domain\Integration\AI\Chat\InvalidChatCommandsConfig;
use App\Domain\Integration\AI\InvalidAIConfiguration;
use App\Domain\Integration\Notification\Shoutrrr\ShoutrrrUrl;
use App\Domain\Settings\IntegrationsSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IntegrationsSettingsTest extends TestCase
{
    private ?string $originalAIApiKey = null;

    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = IntegrationsSettings::fromArray([]);

        $this->assertFalse($settings->isAIIntegrationEnabled());
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
                'provider' => 'openAI',
                'configuration' => ['key' => 'my-key', 'model' => 'cool-model'],
            ],
        ]);

        $this->assertTrue($settings->isAIIntegrationEnabled());
        $this->assertSame(AIProvider::OPEN_AI, $settings->getAIProvider());
        $this->assertSame(['key' => 'my-key', 'model' => 'cool-model'], $settings->getAIConfiguration());
    }

    public function testItKeepsTheAIIntegrationDisabledWhenTheUIWasDisabled(): void
    {
        $settings = IntegrationsSettings::fromArray([
            'ai' => [
                'enabled' => true,
                'enableUI' => false,
                'provider' => 'openAI',
                'configuration' => ['key' => 'my-key', 'model' => 'cool-model'],
            ],
        ]);

        $this->assertFalse($settings->isAIIntegrationEnabled());
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
                'provider' => 'openAI',
                'configuration' => ['model' => 'cool-model'],
            ],
        ]);
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

    public static function provideInvalidAIConfig(): iterable
    {
        yield 'Empty provider name' => [
            [],
            'Provider cannot be empty',
        ];

        yield 'Unsupported provider' => [
            [
                'provider' => 'unknown',
                'configuration' => [
                    'model' => 'lol',
                ],
            ],
            'AI provider "unknown" is not supported',
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
