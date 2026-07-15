<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\DistanceCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class DistanceConditionTest extends TestCase
{
    private DistanceCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'gte', 'value' => 0.0],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration($this->config('gte', 10.0));
    }

    public function testGuardThrowsOnInvalidOperator(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Invalid distance operator "nope".'));

        $this->condition->guardValidConfiguration($this->config('nope', 10.0));
    }

    public function testGuardThrowsOnNegativeValue(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('A "value" of at least 0 kilometer is required.'));

        $this->condition->guardValidConfiguration($this->config('gte', -1.0));
    }

    public function testMatchesWhenActivityDistanceSatisfiesTheOperator(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDistance(Kilometer::from(42.5))->build();

        $this->assertTrue($this->condition->matches($activity, $this->config('gte', 40.0)));
        $this->assertTrue($this->condition->matches($activity, $this->config('gt', 42.0)));
        $this->assertTrue($this->condition->matches($activity, $this->config('lte', 42.5)));
        $this->assertTrue($this->condition->matches($activity, $this->config('eq', 42.5)));
    }

    public function testDoesNotMatchWhenActivityDistanceFailsTheOperator(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDistance(Kilometer::from(42.5))->build();

        $this->assertFalse($this->condition->matches($activity, $this->config('lt', 42.5)));
        $this->assertFalse($this->condition->matches($activity, $this->config('gt', 42.5)));
        $this->assertFalse($this->condition->matches($activity, $this->config('eq', 40.0)));
    }

    private function config(string $operator, float $value): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['operator' => $operator, 'value' => $value]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new DistanceCondition();
    }
}
