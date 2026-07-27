<?php

namespace App\Tests\Domain\Integration\Notification\Shoutrrr;

use App\Domain\Integration\Notification\Shoutrrr\ShoutrrrUrl;
use PHPUnit\Framework\TestCase;

class ShoutrrrUrlTest extends TestCase
{
    public function testWithParams(): void
    {
        $this->assertEquals(
            'ntfy://ntfy.sh/topic',
            (string) ShoutrrrUrl::fromString('ntfy://ntfy.sh/topic')->withParams([])
        );

        $this->assertEquals(
            'ntfy://ntfy.sh/topic?click=the-click',
            (string) ShoutrrrUrl::fromString('ntfy://ntfy.sh/topic')->withParams(['click' => 'the-click'])
        );

        $this->assertEquals(
            'ntfy://ntfy.sh/topic?priority=5&click=the-click',
            (string) ShoutrrrUrl::fromString('ntfy://ntfy.sh/topic?priority=5')->withParams(['click' => 'the-click'])
        );
    }

    public function testIsNtfyUrl(): void
    {
        $this->assertTrue(ShoutrrrUrl::fromString('ntfy://ntfy.sh/topic')->isNtfyUrl());
        $this->assertFalse(ShoutrrrUrl::fromString('gotify://gotify.example.com/token')->isNtfyUrl());
        $this->assertFalse(ShoutrrrUrl::fromString('telegram://token@telegram/?channels=channel')->isNtfyUrl());
    }

    public function testFromDeprecatedNtfyConfig(): void
    {
        $this->assertEquals(
            'ntfy://user:pass@ntfy.sh/topic',
            (string) ShoutrrrUrl::fromDeprecatedNtfyConfig(
                ntfyUrl: 'https://ntfy.sh/topic',
                ntfyUsername: 'user',
                ntfyPassword: 'pass',
            )
        );

        $this->assertEquals(
            'ntfy://ntfy.sh/topic',
            (string) ShoutrrrUrl::fromDeprecatedNtfyConfig(
                ntfyUrl: 'https://ntfy.sh/topic',
                ntfyUsername: null,
                ntfyPassword: null,
            )
        );
    }
}
