<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation;

use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;

class RuleConfigurationTest extends TestCase
{
    public function testFromConfig(): void
    {
        $configuration = RuleConfiguration::fromConfig(['operator' => 'is', 'value' => 10]);

        $this->assertSame(['operator' => 'is', 'value' => 10], $configuration->toArray());
    }

    public function testGet(): void
    {
        $configuration = RuleConfiguration::fromConfig(['value' => 10]);

        $this->assertSame(10, $configuration->get('value'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $configuration = RuleConfiguration::empty();

        $this->assertNull($configuration->get('value'));
        $this->assertSame('fallback', $configuration->get('value', 'fallback'));
    }

    public function testAddMutatesAndReturnsSelf(): void
    {
        $configuration = RuleConfiguration::empty();

        $returned = $configuration->add('operator', 'gt')->add('value', 5);

        $this->assertSame($configuration, $returned);
        $this->assertSame(['operator' => 'gt', 'value' => 5], $configuration->toArray());
    }

    public function testJsonSerialize(): void
    {
        $configuration = RuleConfiguration::fromConfig(['operator' => 'is', 'deviceName' => 'Garmin Edge 130']);

        $this->assertSame(
            '{"operator":"is","deviceName":"Garmin Edge 130"}',
            Json::encode($configuration)
        );
    }
}
