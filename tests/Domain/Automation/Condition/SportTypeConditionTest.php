<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Automation\Condition\SportTypeCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class SportTypeConditionTest extends TestCase
{
    private SportTypeCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'isOneOf', 'sportTypes' => []],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration($this->config('isOneOf', [SportType::RIDE->value, SportType::RUN->value]));
    }

    public function testGuardThrowsOnInvalidOperator(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Invalid sport type operator "nope".'));

        $this->condition->guardValidConfiguration($this->config('nope', [SportType::RIDE->value]));
    }

    public function testGuardThrowsWhenNoSportTypesSelected(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('At least one sport type is required.'));

        $this->condition->guardValidConfiguration($this->config('isOneOf', []));
    }

    public function testGuardThrowsOnInvalidSportType(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Invalid sport type "Flying".'));

        $this->condition->guardValidConfiguration($this->config('isOneOf', [SportType::RIDE->value, 'Flying']));
    }

    public function testMatchesWhenActivitySportTypeIsOneOfTheConfigured(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build();

        $this->assertTrue($this->condition->matches($activity, $this->config('isOneOf', [SportType::RIDE->value, SportType::RUN->value])));
    }

    public function testDoesNotMatchWhenActivitySportTypeIsNotConfigured(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withSportType(SportType::WALK)->build();

        $this->assertFalse($this->condition->matches($activity, $this->config('isOneOf', [SportType::RIDE->value, SportType::RUN->value])));
    }

    public function testIsNoneOfOperatorInvertsTheMatch(): void
    {
        $ride = ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build();
        $walk = ActivityBuilder::fromDefaults()->withSportType(SportType::WALK)->build();

        $this->assertFalse($this->condition->matches($ride, $this->config('isNoneOf', [SportType::RIDE->value, SportType::RUN->value])));
        $this->assertTrue($this->condition->matches($walk, $this->config('isNoneOf', [SportType::RIDE->value, SportType::RUN->value])));
    }

    /**
     * @param list<string> $sportTypes
     */
    private function config(string $operator, array $sportTypes): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['operator' => $operator, 'sportTypes' => $sportTypes]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new SportTypeCondition();
    }
}
