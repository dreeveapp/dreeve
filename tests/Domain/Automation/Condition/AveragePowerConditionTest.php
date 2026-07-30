<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\AveragePowerCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AveragePowerConditionTest extends TestCase
{
    private AveragePowerCondition $condition;

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

        $this->condition->guardValidConfiguration($this->config('gte', 200));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, int $value, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration($this->config($operator, $value));
    }

    #[DataProvider('provideMatchExpectations')]
    public function testMatchesWhenActivityAveragePowerSatisfiesTheOperator(string $operator, int $value, bool $expectedToMatch): void
    {
        $activity = ActivityBuilder::fromDefaults()->withAveragePower(220)->build();

        $this->assertSame($expectedToMatch, $this->condition->matches($activity, $this->config($operator, $value)));
    }

    #[DataProvider('provideOperators')]
    public function testItNeverMatchesWhenTheActivityHasNoPowerData(string $operator): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->condition->matches($activity, $this->config($operator, 100)));
    }

    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['nope', 200, 'Invalid average power operator "nope".'];
        yield 'negative value' => ['gte', -1, 'A "value" of at least 0 is required.'];
    }

    public static function provideMatchExpectations(): iterable
    {
        yield 'gte a smaller value' => ['gte', 200, true];
        yield 'gt a smaller value' => ['gt', 219, true];
        yield 'lte the exact average power' => ['lte', 220, true];
        yield 'eq the exact average power' => ['eq', 220, true];
        yield 'lt the exact average power' => ['lt', 220, false];
        yield 'gt the exact average power' => ['gt', 220, false];
        yield 'eq another value' => ['eq', 200, false];
    }

    public static function provideOperators(): iterable
    {
        yield 'lt' => ['lt'];
        yield 'lte' => ['lte'];
        yield 'gt' => ['gt'];
        yield 'gte' => ['gte'];
        yield 'eq' => ['eq'];
    }

    private function config(string $operator, int $value): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['operator' => $operator, 'value' => $value]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new AveragePowerCondition();
    }
}
