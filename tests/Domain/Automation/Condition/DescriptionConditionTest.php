<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\DescriptionCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DescriptionConditionTest extends TestCase
{
    private DescriptionCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'contains', 'value' => ''],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig([
            'operator' => 'doesNotContain',
            'value' => 'commute',
        ]));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, string $value, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig([
            'operator' => $operator,
            'value' => $value,
        ]));
    }

    public function testMatchesWhenTheDescriptionContainsTheValue(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDescription('Easy commute along the canal')->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'contains',
            'value' => 'commute',
        ])));
    }

    public function testDoesNotMatchWhenTheDescriptionDoesNotContainTheValue(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDescription('Easy commute along the canal')->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'contains',
            'value' => 'race',
        ])));
    }

    public function testMatchingIsCaseInsensitive(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDescription('Easy COMMUTE along the canal')->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'contains',
            'value' => 'commute',
        ])));
    }

    public function testDoesNotContainOperatorInvertsTheMatch(): void
    {
        $commute = ActivityBuilder::fromDefaults()->withDescription('Easy commute along the canal')->build();
        $race = ActivityBuilder::fromDefaults()->withDescription('Sunday race')->build();
        $configuration = RuleConfiguration::fromConfig([
            'operator' => 'doesNotContain',
            'value' => 'commute',
        ]);

        $this->assertFalse($this->condition->matches($commute, $configuration));
        $this->assertTrue($this->condition->matches($race, $configuration));
    }

    public function testAnActivityWithoutDescriptionOnlyMatchesDoesNotContain(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'contains',
            'value' => 'commute',
        ])));
        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'doesNotContain',
            'value' => 'commute',
        ])));
    }

    public function testAnEmptyValueMatchesNothingForEitherOperator(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDescription('Easy commute along the canal')->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'contains',
            'value' => '  ',
        ])));
        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'doesNotContain',
            'value' => '  ',
        ])));
    }

    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['is', 'commute', 'Invalid description operator "is".'];
        yield 'unknown operator' => ['nope', 'commute', 'Invalid description operator "nope".'];
        yield 'empty value' => ['contains', '', 'A "value" is required.'];
        yield 'blank value' => ['contains', '   ', 'A "value" is required.'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new DescriptionCondition();
    }
}
