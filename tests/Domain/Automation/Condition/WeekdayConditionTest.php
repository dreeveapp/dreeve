<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\WeekdayCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'weekdays' => [1, 6, 7]]));
    }

    public function testGuardAcceptsNumericStrings(): void
    {
        $this->expectNotToPerformAssertions();

        // The generic config coercion leaves array elements as submitted strings.
        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'weekdays' => ['1', '5']]));
    }

    /**
     * @param list<int> $weekdays
     */
    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, array $weekdays, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => $operator, 'weekdays' => $weekdays]));
    }

    /**
     * @param list<int> $weekdays
     */
    #[DataProvider('provideMatchExpectations')]
    public function testMatchesAnActivityAgainstTheConfiguredWeekdays(string $operator, array $weekdays, bool $expectedToMatch): void
    {
        // 2023-10-10 is a Tuesday (ISO weekday 2).
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();

        $this->assertSame($expectedToMatch, $this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => $operator, 'weekdays' => $weekdays])));
    }

    /**
     * @return iterable<string, array{string, list<int>, string}>
     */
    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator "is", a single-value operator, not valid for a set condition' => ['is', [1], 'Invalid weekday operator "is".'];
        yield 'no weekdays selected' => ['isOneOf', [], 'At least one weekday is required.'];
        yield 'out of range weekday' => ['isOneOf', [1, 8], 'Invalid weekday "8", expected 1 (Monday) through 7 (Sunday).'];
    }

    /**
     * @return iterable<string, array{string, list<int>, bool}>
     */
    public static function provideMatchExpectations(): iterable
    {
        yield 'isOneOf matches when the activity falls on a configured weekday' => ['isOneOf', [2, 4], true];
        yield 'isOneOf does not match when the activity falls on another weekday' => ['isOneOf', [6, 7], false];
        yield 'isNoneOf does not match on a configured weekday' => ['isNoneOf', [2, 4], false];
        yield 'isNoneOf matches on another weekday' => ['isNoneOf', [6, 7], true];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new WeekdayCondition();
    }
}
