<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\ElevationCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\AppearanceSettings;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ElevationConditionTest extends TestCase
{
    private ElevationCondition $condition;

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

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 100.0]));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, float $value, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => $operator, 'value' => $value]));
    }

    #[DataProvider('provideMatchExpectations')]
    public function testMatchesWhenActivityElevationSatisfiesTheOperator(string $operator, float $value, bool $expectedToMatch): void
    {
        $activity = ActivityBuilder::fromDefaults()->withElevation(Meter::from(123))->build();

        $this->assertSame($expectedToMatch, $this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => $operator, 'value' => $value])));
    }

    public function testMatchesAFlatActivity(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withElevation(Meter::from(0))->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'eq', 'value' => 0.0])));
    }

    public function testMatchesInterpretsTheValueInFeetForImperialUnitSystem(): void
    {
        $condition = $this->conditionFor(UnitSystem::IMPERIAL);
        $activity = ActivityBuilder::fromDefaults()->withElevation(Meter::from(305))->build();

        $this->assertTrue($condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 1000.0])));
        $this->assertFalse($condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 1100.0])));
    }

    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['nope', 100.0, 'Invalid elevation operator "nope".'];
        yield 'negative value' => ['gte', -1.0, 'A "value" of at least 0 is required.'];
    }

    public static function provideMatchExpectations(): iterable
    {
        yield 'gte a smaller value' => ['gte', 100.0, true];
        yield 'gt a smaller value' => ['gt', 122.0, true];
        yield 'lte the exact elevation' => ['lte', 123.0, true];
        yield 'eq the exact elevation' => ['eq', 123.0, true];
        yield 'lt the exact elevation' => ['lt', 123.0, false];
        yield 'gt the exact elevation' => ['gt', 123.0, false];
        yield 'eq another value' => ['eq', 100.0, false];
    }

    private function conditionFor(UnitSystem $unitSystem): ElevationCondition
    {
        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository
            ->method('appearance')
            ->willReturn(AppearanceSettings::fromArray(['unitSystem' => $unitSystem->value]));

        return new ElevationCondition($settingsRepository);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = $this->conditionFor(UnitSystem::METRIC);
    }
}
