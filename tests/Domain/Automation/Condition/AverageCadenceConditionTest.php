<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\AverageCadenceCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AverageCadenceConditionTest extends TestCase
{
    private AverageCadenceCondition $condition;

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

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 80]));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, int $value, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => $operator, 'value' => $value]));
    }

    #[DataProvider('provideMatchExpectations')]
    public function testMatchesWhenActivityAverageCadenceSatisfiesTheOperator(string $operator, int $value, bool $expectedToMatch): void
    {
        $activity = ActivityBuilder::fromDefaults()->withAverageCadence(81)->build();

        $this->assertSame($expectedToMatch, $this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => $operator, 'value' => $value])));
    }

    #[DataProvider('provideOperators')]
    public function testItNeverMatchesWhenTheActivityHasNoCadenceData(string $operator): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => $operator, 'value' => 1])));
    }

    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['nope', 80, 'Invalid average cadence operator "nope".'];
        yield 'negative value' => ['gte', -1, 'A "value" of at least 0 is required.'];
    }

    public static function provideMatchExpectations(): iterable
    {
        yield 'gte a smaller value' => ['gte', 80, true];
        yield 'gt a smaller value' => ['gt', 80, true];
        yield 'lte the exact cadence' => ['lte', 81, true];
        yield 'eq the exact cadence' => ['eq', 81, true];
        yield 'lt the exact cadence' => ['lt', 81, false];
        yield 'gt the exact cadence' => ['gt', 81, false];
        yield 'eq another value' => ['eq', 80, false];
    }

    public static function provideOperators(): iterable
    {
        yield 'lt' => ['lt'];
        yield 'lte' => ['lte'];
        yield 'gt' => ['gt'];
        yield 'gte' => ['gte'];
        yield 'eq' => ['eq'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new AverageCadenceCondition();
    }
}
