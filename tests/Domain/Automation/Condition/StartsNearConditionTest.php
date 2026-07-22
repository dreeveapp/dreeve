<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\StartsNearCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\AppearanceSettings;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class StartsNearConditionTest extends TestCase
{
    private StartsNearCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'within', 'latitude' => 0.0, 'longitude' => 0.0, 'radius' => 500.0],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration($this->config('within', 51.05, 4.0, 1.0));
    }

    public function testGuardThrowsOnInvalidOperator(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Invalid proximity operator "nope".'));

        $this->condition->guardValidConfiguration($this->config('nope', 51.05, 4.0, 1.0));
    }

    public function testGuardThrowsOnOutOfRangeLatitude(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('A "latitude" between -90 and 90 is required.'));

        $this->condition->guardValidConfiguration($this->config('within', 91.0, 4.0, 1.0));
    }

    public function testGuardThrowsOnOutOfRangeLongitude(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('A "longitude" between -180 and 180 is required.'));

        $this->condition->guardValidConfiguration($this->config('within', 51.05, 180.5, 1.0));
    }

    public function testGuardThrowsOnNonPositiveRadius(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('A "radius" greater than 0 is required.'));

        $this->condition->guardValidConfiguration($this->config('within', 51.05, 4.0, 0.0));
    }

    public function testMatchesWhenActivityStartsWithinTheRadius(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withStartingCoordinate($this->coordinate(51.055, 4.0))->build();

        $this->assertTrue($this->condition->matches($activity, $this->config('within', 51.05, 4.0, 1000.0)));
    }

    public function testDoesNotMatchWhenActivityStartsOutsideTheRadius(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withStartingCoordinate($this->coordinate(51.10, 4.0))->build();

        $this->assertFalse($this->condition->matches($activity, $this->config('within', 51.05, 4.0, 1000.0)));
    }

    public function testOutsideOperatorInvertsTheMatch(): void
    {
        $near = ActivityBuilder::fromDefaults()->withStartingCoordinate($this->coordinate(51.055, 4.0))->build();
        $far = ActivityBuilder::fromDefaults()->withStartingCoordinate($this->coordinate(51.10, 4.0))->build();

        $this->assertFalse($this->condition->matches($near, $this->config('outside', 51.05, 4.0, 1000.0)));
        $this->assertTrue($this->condition->matches($far, $this->config('outside', 51.05, 4.0, 1000.0)));
    }

    public function testMatchesInterpretsTheRadiusInFeetForImperialUnitSystem(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withStartingCoordinate($this->coordinate(51.055, 4.0))->build();
        $condition = $this->conditionFor(UnitSystem::IMPERIAL);

        $this->assertFalse($condition->matches($activity, $this->config('within', 51.05, 4.0, 1000.0)));
        $this->assertTrue($condition->matches($activity, $this->config('within', 51.05, 4.0, 2000.0)));
    }

    public function testDoesNotMatchWhenActivityHasNoStartingCoordinate(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->condition->matches($activity, $this->config('within', 51.05, 4.0, 1000.0)));
        $this->assertFalse($this->condition->matches($activity, $this->config('outside', 51.05, 4.0, 1000.0)));
    }

    private function config(string $operator, float $latitude, float $longitude, float $radius): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => $operator,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius,
        ]);
    }

    private function coordinate(float $latitude, float $longitude): Coordinate
    {
        return Coordinate::createFromLatAndLng(
            Latitude::fromString((string) $latitude),
            Longitude::fromString((string) $longitude),
        );
    }

    private function conditionFor(UnitSystem $unitSystem): StartsNearCondition
    {
        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository
            ->method('appearance')
            ->willReturn(AppearanceSettings::fromArray(['unitSystem' => $unitSystem->value]));

        return new StartsNearCondition($settingsRepository);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = $this->conditionFor(UnitSystem::METRIC);
    }
}
