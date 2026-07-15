<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation;

use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRules;
use PHPUnit\Framework\TestCase;

class AutomationRulesTest extends TestCase
{
    public function testItHoldsAutomationRules(): void
    {
        $rules = AutomationRules::fromArray([
            AutomationRuleBuilder::fromDefaults()->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))->build(),
            AutomationRuleBuilder::fromDefaults()->withAutomationRuleId(AutomationRuleId::fromUnprefixed('2'))->build(),
        ]);

        $this->assertCount(2, $rules);
    }

    public function testItOnlyAcceptsAutomationRules(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AutomationRules::fromArray(['not-a-rule']);
    }
}
