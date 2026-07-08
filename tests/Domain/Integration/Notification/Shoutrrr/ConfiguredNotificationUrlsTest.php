<?php

namespace App\Tests\Domain\Integration\Notification\Shoutrrr;

use App\Domain\Integration\Notification\Shoutrrr\ConfiguredNotificationUrls;
use App\Domain\Integration\Notification\Shoutrrr\ShoutrrrUrl;
use PHPUnit\Framework\TestCase;

class ConfiguredNotificationUrlsTest extends TestCase
{
    public function testFromConfig(): void
    {
        $this->assertEquals(
            [
                ShoutrrrUrl::fromString('ntfy://admin:admin@ntfy.sh/topic'),
                ShoutrrrUrl::fromString('discord://token@webhookid?thread_id=123456789'),
            ],
            iterator_to_array(ConfiguredNotificationUrls::fromConfig(
                config: [
                    'ntfy://admin:admin@ntfy.sh/topic',
                    'discord://token@webhookid?thread_id=123456789',
                ],
            )),
        );
    }

    public function testFromConfigItShouldThrow(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Notification service name must be a string'));

        ConfiguredNotificationUrls::fromConfig(
            config: [
                [],
            ],
        );
    }
}
