<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\EnrichActivity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\EnrichActivity\EnrichActivity;
use App\Domain\Activity\EnrichActivity\EnrichActivityCommandHandler;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Integration\Geocoding\Nominatim\Nominatim;
use App\Domain\Integration\Weather\OpenMeteo\OpenMeteo;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Integration\Geocoding\Nominatim\SpyNominatim;
use App\Tests\Domain\Integration\Weather\OpenMeteo\SpyOpenMeteo;

class EnrichActivityCommandHandlerTest extends ContainerTestCase
{
    private ActivityRepository $activityRepository;
    private SpyNominatim $nominatim;
    private SpyOpenMeteo $openMeteo;
    private EnrichActivityCommandHandler $handler;

    public function testHandle(): void
    {
        $this->addActivityThatNeedsEnriching(ActivityId::fromUnprefixed('needs-both'));
        $this->openMeteo->returnHourlyWeatherStats();

        $this->handler->handle(new EnrichActivity(ActivityId::fromUnprefixed('needs-both')));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('needs-both'));

        $this->assertSame('be', $activity->getRouteGeography()->getStartingPointCountryCode());
        $this->assertTrue($activity->getRouteGeography()->isReversedGeocoded());
        $this->assertSame(18.5, $activity->getWeather()?->getTemperatureInCelsius()->toFloat());
        $this->assertFalse($activity->isMissingReverseGeocoding());
        $this->assertFalse($activity->isMissingWeather());
    }

    public function testHandleKeepsThePassedThroughCountries(): void
    {
        $this->addActivityThatNeedsEnriching(
            activityId: ActivityId::fromUnprefixed('has-countries'),
            routeGeography: RouteGeography::create([RouteGeography::PASSED_TROUGH_COUNTRIES => ['BE']]),
        );
        $this->openMeteo->returnHourlyWeatherStats();

        $this->handler->handle(new EnrichActivity(ActivityId::fromUnprefixed('has-countries')));

        $this->assertSame(
            ['be'],
            $this->activityRepository->find(ActivityId::fromUnprefixed('has-countries'))->getRouteGeography()->getPassedThroughCountries()
        );
    }

    public function testHandleWhenNominatimCannotBeReached(): void
    {
        $this->addActivityThatNeedsEnriching(ActivityId::fromUnprefixed('no-nominatim'));
        $this->openMeteo->returnHourlyWeatherStats();
        $this->nominatim->triggerExceptionOnNextCall();

        try {
            $this->handler->handle(new EnrichActivity(ActivityId::fromUnprefixed('no-nominatim')));
            $this->fail('Expected CouldNotProcessCommand');
        } catch (CouldNotProcessCommand $exception) {
            $this->assertSame(
                'Could not reach Nominatim to reverse geocode the location of this activity.',
                $exception->getMessage()
            );
        }

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('no-nominatim'));

        $this->assertTrue($activity->isMissingReverseGeocoding());
        $this->assertTrue($activity->isMissingWeather());
    }

    public function testHandleWhenOpenMeteoCannotBeReached(): void
    {
        $this->addActivityThatNeedsEnriching(ActivityId::fromUnprefixed('no-open-meteo'));
        $this->openMeteo->triggerExceptionOnNextCall();

        try {
            $this->handler->handle(new EnrichActivity(ActivityId::fromUnprefixed('no-open-meteo')));
            $this->fail('Expected CouldNotProcessCommand');
        } catch (CouldNotProcessCommand $exception) {
            $this->assertSame(
                'Could not reach Open-Meteo to look up the weather for this activity.',
                $exception->getMessage()
            );
        }

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('no-open-meteo'));

        $this->assertTrue($activity->isMissingReverseGeocoding());
        $this->assertTrue($activity->isMissingWeather());
    }

    public function testHandleWhenThereIsNothingToEnrich(): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('virtual'))
                ->withSportType(SportType::VIRTUAL_RIDE)
                ->build(),
            []
        ));
        $this->nominatim->triggerExceptionOnNextCall();
        $this->openMeteo->triggerExceptionOnNextCall();

        $this->handler->handle(new EnrichActivity(ActivityId::fromUnprefixed('virtual')));

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('virtual'));

        $this->assertFalse($activity->isMissingReverseGeocoding());
        $this->assertFalse($activity->isMissingWeather());
    }

    private function addActivityThatNeedsEnriching(ActivityId $activityId, ?RouteGeography $routeGeography = null): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withSportType(SportType::WALK)
                ->withStartDateTime(SerializableDateTime::fromString('2023-07-29 10:00:00'))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                    Latitude::fromString('50.80'),
                    Longitude::fromString('4.94'),
                ))
                ->withRouteGeography($routeGeography ?? RouteGeography::create([]))
                ->build(),
            []
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->nominatim = $this->getContainer()->get(Nominatim::class);
        $this->openMeteo = $this->getContainer()->get(OpenMeteo::class);

        $this->handler = new EnrichActivityCommandHandler(
            $this->activityRepository,
            $this->nominatim,
            $this->openMeteo,
        );
    }
}
