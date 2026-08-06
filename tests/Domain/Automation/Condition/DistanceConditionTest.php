<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\DistanceCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\AppearanceSettings;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\UnitSystem;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 10.0]));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, float $value, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => $operator, 'value' => $value]));
    }

    #[DataProvider('provideMatchExpectations')]
    public function testMatchesWhenActivityDistanceSatisfiesTheOperator(string $operator, float $value, bool $expectedToMatch): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDistance(Kilometer::from(42.5))->build();

        $this->assertSame($expectedToMatch, $this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => $operator, 'value' => $value])));
    }

    public function testMatchesInterpretsTheValueInMilesForImperialUnitSystem(): void
    {
        $condition = $this->conditionFor(UnitSystem::IMPERIAL);
        $activity = ActivityBuilder::fromDefaults()->withDistance(Kilometer::from(16.1))->build();

        $this->assertTrue($condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 10.0])));
        $this->assertFalse($condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 12.0])));
    }

    /**
     * @return iterable<string, array{string, float, string}>
     */
    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['nope', 10.0, 'Invalid distance operator "nope".'];
        yield 'negative value' => ['gte', -1.0, 'A "value" of at least 0 is required.'];
    }

    /**
     * @return iterable<string, array{string, float, bool}>
     */
    public static function provideMatchExpectations(): iterable
    {
        yield 'gte a smaller value' => ['gte', 40.0, true];
        yield 'gt a smaller value' => ['gt', 42.0, true];
        yield 'lte the exact distance' => ['lte', 42.5, true];
        yield 'eq the exact distance' => ['eq', 42.5, true];
        yield 'lt the exact distance' => ['lt', 42.5, false];
        yield 'gt the exact distance' => ['gt', 42.5, false];
        yield 'eq another value' => ['eq', 40.0, false];
    }

    private function conditionFor(UnitSystem $unitSystem): DistanceCondition
    {
        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository
            ->method('appearance')
            ->willReturn(AppearanceSettings::fromArray(['unitSystem' => $unitSystem->value]));

        return new DistanceCondition($settingsRepository);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = $this->conditionFor(UnitSystem::METRIC);
    }
}
