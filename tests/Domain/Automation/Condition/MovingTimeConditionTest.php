<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\MovingTimeCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MovingTimeConditionTest extends TestCase
{
    private MovingTimeCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'gte', 'value' => 0],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 90]));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, int $value, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => $operator, 'value' => $value]));
    }

    #[DataProvider('provideMatchExpectations')]
    public function testMatchesWhenActivityMovingTimeSatisfiesTheOperator(string $operator, int $value, bool $expectedToMatch): void
    {
        $activity = ActivityBuilder::fromDefaults()->withMovingTimeInSeconds(5400)->build();

        $this->assertSame($expectedToMatch, $this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => $operator, 'value' => $value])));
    }

    public function testItDoesNotMatchExactlyWhenTheActivityRanOverTheFullMinute(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withMovingTimeInSeconds(5430)->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'eq', 'value' => 90])));
        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'gt', 'value' => 90])));
    }

    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['nope', 90, 'Invalid moving time operator "nope".'];
        yield 'negative value' => ['gte', -1, 'A "value" of at least 0 is required.'];
    }

    public static function provideMatchExpectations(): iterable
    {
        yield 'gte a smaller value' => ['gte', 89, true];
        yield 'gte the exact duration' => ['gte', 90, true];
        yield 'gt a smaller value' => ['gt', 89, true];
        yield 'lte the exact duration' => ['lte', 90, true];
        yield 'eq the exact duration' => ['eq', 90, true];
        yield 'lt the exact duration' => ['lt', 90, false];
        yield 'gt the exact duration' => ['gt', 90, false];
        yield 'eq another value' => ['eq', 89, false];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new MovingTimeCondition();
    }
}
