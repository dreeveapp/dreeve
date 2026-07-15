<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition\ConfiguredCondition;

use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\RuleConfiguration;
use PHPUnit\Framework\TestCase;

class ConfiguredConditionTest extends TestCase
{
    public function testGetters(): void
    {
        $configuration = RuleConfiguration::fromConfig(['deviceName' => 'Garmin']);
        $configured = new ConfiguredCondition(ConditionType::DEVICE, $configuration);

        $this->assertSame(ConditionType::DEVICE, $configured->getType());
        $this->assertSame($configuration, $configured->getConfiguration());
    }

    public function testCollectionOnlyAcceptsConfiguredConditions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfiguredConditions::fromArray(['not-a-condition']);
    }
}
