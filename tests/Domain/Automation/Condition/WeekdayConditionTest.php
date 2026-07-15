<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\WeekdayCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class WeekdayConditionTest extends TestCase
{
    private WeekdayCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'isOneOf', 'weekdays' => []],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration($this->config('isOneOf', [1, 6, 7]));
    }

    public function testGuardAcceptsNumericStrings(): void
    {
        $this->expectNotToPerformAssertions();

        // The generic config coercion leaves array elements as submitted strings.
        $this->condition->guardValidConfiguration($this->config('isOneOf', ['1', '5']));
    }

    public function testGuardThrowsOnInvalidOperator(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Invalid weekday operator "is".'));

        // "is" is a single-value operator, not valid for a set condition.
        $this->condition->guardValidConfiguration($this->config('is', [1]));
    }

    public function testGuardThrowsWhenNoWeekdaysSelected(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('At least one weekday is required.'));

        $this->condition->guardValidConfiguration($this->config('isOneOf', []));
    }

    public function testGuardThrowsOnOutOfRangeWeekday(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Invalid weekday "8", expected 1 (Monday) through 7 (Sunday).'));

        $this->condition->guardValidConfiguration($this->config('isOneOf', [1, 8]));
    }

    public function testMatchesWhenActivityFallsOnAConfiguredWeekday(): void
    {
        // 2023-10-10 is a Tuesday (ISO weekday 2).
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();

        $this->assertTrue($this->condition->matches($activity, $this->config('isOneOf', [2, 4])));
    }

    public function testDoesNotMatchWhenActivityFallsOnAnotherWeekday(): void
    {
        // 2023-10-10 is a Tuesday (ISO weekday 2).
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();

        $this->assertFalse($this->condition->matches($activity, $this->config('isOneOf', [6, 7])));
    }

    public function testIsNoneOfOperatorInvertsTheMatch(): void
    {
        // 2023-10-10 is a Tuesday (ISO weekday 2).
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();

        $this->assertFalse($this->condition->matches($activity, $this->config('isNoneOf', [2, 4])));
        $this->assertTrue($this->condition->matches($activity, $this->config('isNoneOf', [6, 7])));
    }

    /**
     * @param list<int|string> $weekdays
     */
    private function config(string $operator, array $weekdays): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['operator' => $operator, 'weekdays' => $weekdays]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new WeekdayCondition();
    }
}
