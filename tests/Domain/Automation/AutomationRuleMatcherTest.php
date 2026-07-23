<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation;

use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleMatcher;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Automation\Fixture\DeviceCondition;
use App\Tests\Domain\Automation\Fixture\DistanceCondition;
use PHPUnit\Framework\TestCase;

class AutomationRuleMatcherTest extends TestCase
{
    private AutomationRuleMatcher $matcher;

    public function testMatchesWhenAllConditionsMatch(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withDeviceName('Garmin')
            ->withDistance(Kilometer::from(80.0))
            ->build();

        $rule = $this->rule('1', conditions: ConfiguredConditions::fromArray([
            new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['deviceName' => 'Garmin'])),
            new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['minKm' => 50])),
        ]));

        $this->assertTrue($this->matcher->matches($rule, $activity));
    }

    public function testDoesNotMatchWhenAConditionFails(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDeviceName('Wahoo')->build();

        $this->assertFalse($this->matcher->matches(
            $this->rule('1', conditions: $this->deviceIs('Garmin')),
            $activity,
        ));
    }

    public function testARuleWithoutConditionsNeverMatches(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->matcher->matches(
            $this->rule('1', conditions: ConfiguredConditions::empty()),
            $activity,
        ));
    }

    public function testEvaluateConditionsReportsEveryConditionWithoutShortCircuiting(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withDeviceName('Wahoo')
            ->withDistance(Kilometer::from(80.0))
            ->build();

        // First condition fails; a short-circuiting matcher would never look at the second.
        $rule = $this->rule('1', conditions: ConfiguredConditions::fromArray([
            new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['deviceName' => 'Garmin'])),
            new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['minKm' => 50])),
        ]));

        $results = $this->matcher->evaluateConditions($rule, $activity);

        $this->assertCount(2, $results);
        $this->assertSame(ConditionType::DEVICE, $results[0]->getType());
        $this->assertFalse($results[0]->isMatched());
        $this->assertSame(ConditionType::DISTANCE, $results[1]->getType());
        $this->assertTrue($results[1]->isMatched());
    }

    public function testUnregisteredConditionTypesAreIgnored(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        // SPORT_TYPE is not registered in the fixture Conditions, so it is skipped entirely.
        $rule = $this->rule('1', conditions: ConfiguredConditions::fromArray([
            new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['sportTypes' => ['Ride']])),
        ]));

        $this->assertCount(0, $this->matcher->evaluateConditions($rule, $activity));

        $this->assertFalse(
            $this->matcher->matches($rule, $activity),
            'A rule with only unregistered condition types has no condition to satisfy, so it never matches.',
        );
    }

    private function deviceIs(string $deviceName): ConfiguredConditions
    {
        return ConfiguredConditions::fromArray([
            new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['deviceName' => $deviceName])),
        ]);
    }

    private function rule(string $id, ConfiguredConditions $conditions): \App\Domain\Automation\AutomationRule
    {
        return AutomationRuleBuilder::fromDefaults()
            ->withAutomationRuleId(AutomationRuleId::fromUnprefixed($id))
            ->withConditions($conditions)
            ->build();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new AutomationRuleMatcher(new Conditions([
            new DeviceCondition(),
            new DistanceCondition(),
        ]));
    }
}
