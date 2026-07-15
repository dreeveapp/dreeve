<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action\ConfiguredAction;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\RuleConfiguration;
use PHPUnit\Framework\TestCase;

class ConfiguredActionTest extends TestCase
{
    public function testGetters(): void
    {
        $configuration = RuleConfiguration::fromConfig(['name' => 'Commute']);
        $configured = new ConfiguredAction(ActionType::SET_NAME, $configuration);

        $this->assertSame(ActionType::SET_NAME, $configured->getType());
        $this->assertSame($configuration, $configured->getConfiguration());
    }

    public function testCollectionOnlyAcceptsConfiguredActions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfiguredActions::fromArray(['not-an-action']);
    }
}
