<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Activity\Route\ActivityRouteCoordinates;
use App\Domain\Activity\Stream\DbalActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Automation\Condition\PassesNearCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class PassesNearConditionTest extends ContainerTestCase
{
    private DbalActivityStreamRepository $activityStreamRepository;
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

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig([
            'operator' => 'within',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 0.0,
        ]));
    }

    public function testMatchesWhenAnyIntermediatePointIsWithinTheRadius(): void
    {
        // Strava truncates the summary polyline inside the athlete's privacy zone, so it is blind to
        // the beginning and the end of the ride. The latlng stream is not and takes precedence.
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [45.0, 1.0]]))
            ->build();
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[48.0, 2.0], [51.055, 4.0], [45.0, 1.0]])
            ->build());

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
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

    public function testDoesNotMatchWhenNoPointComesNear(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [45.0, 1.0], [46.0, 3.0]]))
            ->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ])));
    }

    public function testOutsideOperatorMatchesOnlyWhenTheRouteNeverComesNear(): void
    {
        $passesNear = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [51.055, 4.0], [45.0, 1.0]]))
            ->build();
        $neverNear = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [45.0, 1.0], [46.0, 3.0]]))
            ->build();
        $configuration = RuleConfiguration::fromConfig([
            'operator' => 'outside',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ]);

        $this->assertFalse($this->condition->matches($passesNear, $configuration));
        $this->assertTrue($this->condition->matches($neverNear, $configuration));
    }

    public function testMatchesInterpretsTheRadiusInFeetForImperialUnitSystem(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [51.055, 4.0], [45.0, 1.0]]))
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

    public function testDoesNotMatchWhenActivityHasNoPolyline(): void
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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityStreamRepository = new DbalActivityStreamRepository($this->getConnection());
        $this->condition = new PassesNearCondition(
            $this->getContainer()->get(SettingsRepository::class),
            new ActivityRouteCoordinates($this->activityStreamRepository)
        );
    }
}
