<?php

namespace App\Tests\Domain\Strava\Webhook;

use App\Domain\Strava\Webhook\InvalidWebhookConfig;
use App\Domain\Strava\Webhook\WebhookConfig;
use Cron\CronExpression;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class WebhookConfigTest extends TestCase
{
    public function testFromArrayWithDefaults(): void
    {
        $config = WebhookConfig::fromArray([]);

        $this->assertFalse($config->isEnabled());
        $this->assertEmpty($config->getVerifyToken());
        $this->assertEquals(
            new CronExpression('* * * * *'),
            $config->getCronExpression()
        );
    }

    public function testFromArrayWithValues(): void
    {
        $config = WebhookConfig::fromArray([
            'enabled' => true,
            'verifyToken' => 'secret-token',
            'checkIntervalInMinutes' => 4,
        ]);

        $this->assertTrue($config->isEnabled());
        $this->assertEquals('secret-token', $config->getVerifyToken());
        $this->assertEquals(
            new CronExpression('*/4 * * * *'),
            $config->getCronExpression()
        );
    }

    public function testItShouldThrowWhenEmptyVerifyToken(): void
    {
        $this->expectExceptionObject(new InvalidWebhookConfig('"verifyToken" property cannot be empty.'));
        WebhookConfig::fromArray([
            'enabled' => true,
        ]);
    }

    #[TestWith(data: ['lol'], name: 'not an integer')]
    #[TestWith(data: [0], name: 'lower than one')]
    #[TestWith(data: [61], name: 'higher than sixty')]
    public function testItShouldThrowWhenCheckIntervalIsInvalid(string|int $checkIntervalInMinutes): void
    {
        $this->expectExceptionObject(new InvalidWebhookConfig('"checkIntervalInMinutes" property must be valid integer between 1 and 60.'));
        WebhookConfig::fromArray([
            'enabled' => true,
            'verifyToken' => 'secret-token',
            'checkIntervalInMinutes' => $checkIntervalInMinutes,
        ]);
    }
}
