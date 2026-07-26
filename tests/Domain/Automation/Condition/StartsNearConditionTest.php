<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Activity\Route\ActivityRouteCoordinates;
use App\Domain\Activity\Stream\DbalActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Automation\Condition\StartsNearCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class StartsNearConditionTest extends ContainerTestCase
{
    private DbalActivityStreamRepository $activityStreamRepository;
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

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig([
            'operator' => 'within',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1.0,
        ]));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, float $latitude, float $longitude, float $radius, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig([
            'operator' => $operator,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius,
        ]));
    }

    public function testMatchesWhenActivityStartsWithinTheRadius(): void
    {
        // The latlng stream is the most accurate source and takes precedence over the starting coordinate.
        $activity = ActivityBuilder::fromDefaults()
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('51.10'), Longitude::fromString('4.0')))
            ->build();
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[51.055, 4.0], [51.10, 4.0]])
            ->build());

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ])));
    }

    public function testDoesNotMatchWhenActivityStartsOutsideTheRadius(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('51.10'), Longitude::fromString('4.0')))
            ->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ])));
    }

    public function testOutsideOperatorInvertsTheMatch(): void
    {
        $near = ActivityBuilder::fromDefaults()
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('51.055'), Longitude::fromString('4.0')))
            ->build();
        $far = ActivityBuilder::fromDefaults()
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('51.10'), Longitude::fromString('4.0')))
            ->build();
        $configuration = RuleConfiguration::fromConfig([
            'operator' => 'outside',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ]);

        $this->assertFalse($this->condition->matches($near, $configuration));
        $this->assertTrue($this->condition->matches($far, $configuration));
    }

    public function testMatchesInterpretsTheRadiusInFeetForImperialUnitSystem(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('51.055'), Longitude::fromString('4.0')))
            ->build();
        $this->getContainer()->get(SettingsRepository::class)->save(SettingsGroup::APPEARANCE, ['unitSystem' => 'imperial']);

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ])));
        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 2000.0,
        ])));
    }

    public function testDoesNotMatchWhenActivityHasNoStartingCoordinate(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ])));
        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'outside',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ])));
    }

    /**
     * @return iterable<string, array{string, float, float, float, string}>
     */
    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['nope', 51.05, 4.0, 1.0, 'Invalid proximity operator "nope".'];
        yield 'out of range latitude' => ['within', 91.0, 4.0, 1.0, 'A "latitude" between -90 and 90 is required.'];
        yield 'out of range longitude' => ['within', 51.05, 180.5, 1.0, 'A "longitude" between -180 and 180 is required.'];
        yield 'non-positive radius' => ['within', 51.05, 4.0, 0.0, 'A "radius" greater than 0 is required.'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityStreamRepository = new DbalActivityStreamRepository($this->getConnection());
        $this->condition = new StartsNearCondition(
            $this->getContainer()->get(SettingsRepository::class),
            new ActivityRouteCoordinates($this->activityStreamRepository)
        );
    }
}
