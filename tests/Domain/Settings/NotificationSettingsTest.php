<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Integration\Notification\Shoutrrr\ShoutrrrUrl;
use App\Domain\Settings\NotificationSettings;
use PHPUnit\Framework\TestCase;

class NotificationSettingsTest extends TestCase
{
    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = NotificationSettings::fromArray([]);

        $this->assertCount(0, iterator_to_array($settings->getConfiguredNotificationUrls()));
        $this->assertTrue($settings->shouldNotifyOnSuccessfulImport());
    }

    public function testItDisablesTheSuccessfulBuildNotification(): void
    {
        $settings = NotificationSettings::fromArray([
            'notifications' => ['notifyOnSuccessfulBuild' => '0'],
        ]);

        $this->assertFalse($settings->shouldNotifyOnSuccessfulImport());
    }

    public function testItBuildsConfiguredNotificationUrls(): void
    {
        $settings = NotificationSettings::fromArray([
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
}
