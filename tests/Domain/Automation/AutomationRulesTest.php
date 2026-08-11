<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation;

use App\Domain\Automation\AutomationRule;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleIds;
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

    public function testEnabled(): void
    {
        $rules = AutomationRules::fromArray([
            AutomationRuleBuilder::fromDefaults()->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))->withIsEnabled(true)->build(),
            AutomationRuleBuilder::fromDefaults()->withAutomationRuleId(AutomationRuleId::fromUnprefixed('2'))->withIsEnabled(false)->build(),
            AutomationRuleBuilder::fromDefaults()->withAutomationRuleId(AutomationRuleId::fromUnprefixed('3'))->withIsEnabled(true)->build(),
        ]);

        $this->assertSame(
            ['automationRule-1', 'automationRule-3'],
            $rules->enabled()->map(static fn (AutomationRule $rule): string => (string) $rule->getId())
        );
    }

    public function testOnly(): void
    {
        $rules = AutomationRules::fromArray([
            AutomationRuleBuilder::fromDefaults()->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))->build(),
            AutomationRuleBuilder::fromDefaults()->withAutomationRuleId(AutomationRuleId::fromUnprefixed('2'))->build(),
            AutomationRuleBuilder::fromDefaults()->withAutomationRuleId(AutomationRuleId::fromUnprefixed('3'))->build(),
        ]);

        $filtered = $rules->only(AutomationRuleIds::fromArray([
            AutomationRuleId::fromUnprefixed('3'),
            AutomationRuleId::fromUnprefixed('1'),
            AutomationRuleId::fromUnprefixed('does-not-exist'),
        ]));

        $this->assertSame(
            ['automationRule-1', 'automationRule-3'],
            $filtered->map(static fn (AutomationRule $rule): string => (string) $rule->getId()),
            'The collections own order is kept, not the order of the ids handed in.'
        );
        $this->assertTrue($rules->only(AutomationRuleIds::empty())->isEmpty());
    }
}
