<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Integration\AI\Chat\InvalidChatCommandsConfig;
use App\Domain\Integration\Notification\Shoutrrr\ShoutrrrUrl;
use App\Domain\Settings\IntegrationsSettings;
use PHPUnit\Framework\TestCase;

class IntegrationsSettingsTest extends TestCase
{
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
            ],
        ]);

        $this->assertTrue($settings->isAIIntegrationEnabled());
        $this->assertFalse($settings->isAIIntegrationWithUIEnabled());
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
        // The empty service is filtered out.
        $this->assertCount(2, $urls);
    }

    public function testItThrowsForAnInvalidChatCommand(): void
    {
        $this->expectException(InvalidChatCommandsConfig::class);

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
}
