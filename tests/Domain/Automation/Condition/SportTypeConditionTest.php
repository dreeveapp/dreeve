<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Automation\Condition\SportTypeCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => [SportType::RIDE->value, SportType::RUN->value]]));
    }

    /**
     * @param list<string> $sportTypes
     */
    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, array $sportTypes, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => $operator, 'sportTypes' => $sportTypes]));
    }

    public function testMatchesWhenActivitySportTypeIsOneOfTheConfigured(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => [SportType::RIDE->value, SportType::RUN->value]])));
    }

    public function testDoesNotMatchWhenActivitySportTypeIsNotConfigured(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withSportType(SportType::WALK)->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => [SportType::RIDE->value, SportType::RUN->value]])));
    }

    public function testIsNoneOfOperatorInvertsTheMatch(): void
    {
        $ride = ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build();
        $walk = ActivityBuilder::fromDefaults()->withSportType(SportType::WALK)->build();

        $this->assertFalse($this->condition->matches($ride, RuleConfiguration::fromConfig(['operator' => 'isNoneOf', 'sportTypes' => [SportType::RIDE->value, SportType::RUN->value]])));
        $this->assertTrue($this->condition->matches($walk, RuleConfiguration::fromConfig(['operator' => 'isNoneOf', 'sportTypes' => [SportType::RIDE->value, SportType::RUN->value]])));
    }

    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['nope', [SportType::RIDE->value], 'Invalid sport type operator "nope".'];
        yield 'no sport types selected' => ['isOneOf', [], 'At least one sport type is required.'];
        yield 'invalid sport type' => ['isOneOf', [SportType::RIDE->value, 'Flying'], 'Invalid sport type "Flying".'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new SportTypeCondition();
    }
}
