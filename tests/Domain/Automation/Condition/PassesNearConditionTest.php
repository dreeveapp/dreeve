<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\PassesNearCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\AppearanceSettings;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class PassesNearConditionTest extends TestCase
{
    private PassesNearCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'within', 'latitude' => 0.0, 'longitude' => 0.0, 'radius' => 500.0],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardThrowsOnNonPositiveRadius(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('A "radius" greater than 0 is required.'));

        $this->condition->guardValidConfiguration($this->config('within', 51.05, 4.0, 0.0));
    }

    public function testMatchesWhenAnyIntermediatePointIsWithinTheRadius(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [51.055, 4.0], [45.0, 1.0]]))
            ->build();

        $this->assertTrue($this->condition->matches($activity, $this->config('within', 51.05, 4.0, 1000.0)));
    }

    public function testDoesNotMatchWhenNoPointComesNear(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [45.0, 1.0], [46.0, 3.0]]))
            ->build();

        $this->assertFalse($this->condition->matches($activity, $this->config('within', 51.05, 4.0, 1000.0)));
    }

    public function testOutsideOperatorMatchesOnlyWhenTheRouteNeverComesNear(): void
    {
        $passesNear = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [51.055, 4.0], [45.0, 1.0]]))
            ->build();
        $neverNear = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [45.0, 1.0], [46.0, 3.0]]))
            ->build();

        $this->assertFalse($this->condition->matches($passesNear, $this->config('outside', 51.05, 4.0, 1000.0)));
        $this->assertTrue($this->condition->matches($neverNear, $this->config('outside', 51.05, 4.0, 1000.0)));
    }

    public function testMatchesInterpretsTheRadiusInFeetForImperialUnitSystem(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [51.055, 4.0], [45.0, 1.0]]))
            ->build();
        $condition = $this->conditionFor(UnitSystem::IMPERIAL);

        $this->assertFalse($condition->matches($activity, $this->config('within', 51.05, 4.0, 1000.0)));
        $this->assertTrue($condition->matches($activity, $this->config('within', 51.05, 4.0, 2000.0)));
    }

    public function testDoesNotMatchWhenActivityHasNoPolyline(): void
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

    private function conditionFor(UnitSystem $unitSystem): PassesNearCondition
    {
        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository
            ->method('appearance')
            ->willReturn(AppearanceSettings::fromArray(['unitSystem' => $unitSystem->value]));

        return new PassesNearCondition($settingsRepository);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = $this->conditionFor(UnitSystem::METRIC);
    }
}
