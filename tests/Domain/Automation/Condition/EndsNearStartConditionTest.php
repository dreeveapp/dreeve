<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Activity\Route\ActivityRouteCoordinates;
use App\Domain\Activity\Stream\DbalActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Automation\Condition\EndsNearStartCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class EndsNearStartConditionTest extends ContainerTestCase
{
    private DbalActivityStreamRepository $activityStreamRepository;
    private EndsNearStartCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'within', 'radius' => 500.0],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig([
            'operator' => 'outside',
            'radius' => 1.0,
        ]));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(array $config, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig($config));
    }

    public function testMatchesWhenActivityEndsWithinTheRadiusOfItsStart(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[51.05, 4.0], [51.30, 4.2], [51.055, 4.0]])
            ->build());

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'radius' => 1000.0,
        ])));
        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'outside',
            'radius' => 1000.0,
        ])));
    }

    public function testMatchesWhenActivityEndsOutsideTheRadiusOfItsStart(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[51.05, 4.0], [51.10, 4.0]])
            ->build());

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'radius' => 1000.0,
        ])));
        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'outside',
            'radius' => 1000.0,
        ])));
    }

    public function testFallsBackToTheStartingCoordinateAndPolylineWithoutALatLngStream(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('51.05'), Longitude::fromString('4.0')))
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[51.06, 4.0], [51.30, 4.2], [51.055, 4.0]]))
            ->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'radius' => 1000.0,
        ])));
    }

    public function testMatchesInterpretsTheRadiusInFeetForImperialUnitSystem(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[51.05, 4.0], [51.055, 4.0]])
            ->build());
        $this->getContainer()->get(SettingsRepository::class)->save(SettingsName::UNIT_SYSTEM, 'imperial');

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'radius' => 1000.0,
        ])));
        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'radius' => 2000.0,
        ])));
    }

    public function testDoesNotMatchWhenActivityHasNoCoordinates(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'within',
            'radius' => 1000.0,
        ])));
        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig([
            'operator' => 'outside',
            'radius' => 1000.0,
        ])));
    }

    public function testDescribeValue(): void
    {
        $this->assertSame(
            'within radius 750 m',
            $this->condition->describeValue($this->getContainer()->get(TranslatorInterface::class), RuleConfiguration::fromConfig([
                'operator' => 'within',
                'radius' => 750.0,
            ]))
        );
    }

    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => [['operator' => 'nope', 'radius' => 1.0], 'Invalid proximity operator "nope".'];
        yield 'non-proximity operator' => [['operator' => 'isNot', 'radius' => 1.0], 'Invalid proximity operator "isNot".'];
        yield 'non-positive radius' => [['operator' => 'within', 'radius' => 0.0], 'A "radius" greater than 0 is required.'];
        yield 'missing radius' => [['operator' => 'within'], 'A "radius" greater than 0 is required.'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityStreamRepository = new DbalActivityStreamRepository($this->getConnection());
        $this->condition = new EndsNearStartCondition(
            $this->getContainer()->get(SettingsRepository::class),
            new ActivityRouteCoordinates($this->activityStreamRepository)
        );
    }
}
