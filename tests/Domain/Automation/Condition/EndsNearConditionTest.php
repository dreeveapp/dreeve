<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Activity\Route\ActivityRouteCoordinates;
use App\Domain\Activity\Stream\DbalActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Automation\Condition\EndsNearCondition;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class EndsNearConditionTest extends ContainerTestCase
{
    private DbalActivityStreamRepository $activityStreamRepository;
    private EndsNearCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'within', 'latitude' => 0.0, 'longitude' => 0.0, 'radius' => 500.0],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testMatchesWhenActivityEndsWithinTheRadius(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[50.0, 3.0], [51.055, 4.0]]))
            ->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ])));
    }

    public function testDoesNotMatchWhenActivityEndsOutsideTheRadius(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[50.0, 3.0], [51.10, 4.0]]))
            ->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ])));
    }

    public function testMatchingIgnoresTheStartAndOnlyLooksAtTheEnd(): void
    {
        // Strava truncates the summary polyline inside the athlete's privacy zone, so it stops well
        // before the real end of the ride. The latlng stream is not truncated and takes precedence.
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[50.0, 3.0], [51.10, 4.0]]))
            ->build();
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[48.0, 2.0], [51.055, 4.0]])
            ->build());

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ])));
    }

    public function testOutsideOperatorInvertsTheMatch(): void
    {
        $endsNear = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[50.0, 3.0], [51.055, 4.0]]))
            ->build();
        $endsFar = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[50.0, 3.0], [51.10, 4.0]]))
            ->build();
        $configuration = RuleConfiguration::fromConfig([
            'operator' => 'outside',
            'latitude' => 51.05,
            'longitude' => 4.0,
            'radius' => 1000.0,
        ]);

        $this->assertFalse($this->condition->matches($endsNear, $configuration));
        $this->assertTrue($this->condition->matches($endsFar, $configuration));
    }

    public function testMatchesInterpretsTheRadiusInFeetForImperialUnitSystem(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[50.0, 3.0], [51.055, 4.0]]))
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
        $this->condition = new EndsNearCondition(
            $this->getContainer()->get(SettingsRepository::class),
            new ActivityRouteCoordinates($this->activityStreamRepository)
        );
    }
}
