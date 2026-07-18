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

    public function testGetString(): void
    {
        $configuration = RuleConfiguration::fromConfig(['operator' => 'is']);

        $this->assertSame('is', $configuration->getString('operator'));
    }

    public function testGetStringThrowsWhenMissing(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Configuration value operator is missing'));

        RuleConfiguration::empty()->getString('operator');
    }

    public function testGetStringThrowsWhenNotAString(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Configuration value operator is not a string'));

        RuleConfiguration::fromConfig(['operator' => 10])->getString('operator');
    }

    public function testGetNumber(): void
    {
        $configuration = RuleConfiguration::fromConfig(['value' => 10, 'radius' => 1.5]);

        $this->assertSame(10, $configuration->getNumber('value'));
        $this->assertSame(1.5, $configuration->getNumber('radius'));
    }

    public function testGetNumberThrowsWhenMissing(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Configuration value value is missing'));

        RuleConfiguration::empty()->getNumber('value');
    }

    public function testGetNumberThrowsWhenNotANumber(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Configuration value value is not a number'));

        RuleConfiguration::fromConfig(['value' => '10'])->getNumber('value');
    }

    public function testGetArray(): void
    {
        $configuration = RuleConfiguration::fromConfig(['sportTypes' => ['a' => 'Ride', 'b' => 'Run']]);

        $this->assertSame(['Ride', 'Run'], $configuration->getArray('sportTypes'), 'Keys must be discarded.');
        $this->assertSame([], $configuration->getArray('missing'));
    }

    public function testGetArrayThrowsWhenNotAnArray(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Configuration value sportTypes is not an array'));

        RuleConfiguration::fromConfig(['sportTypes' => 'Ride'])->getArray('sportTypes');
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
