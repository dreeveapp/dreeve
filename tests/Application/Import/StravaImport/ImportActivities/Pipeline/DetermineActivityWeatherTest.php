<?php

namespace App\Tests\Application\Import\StravaImport\ImportActivities\Pipeline;

use App\Application\Import\StravaImport\ImportActivities\Pipeline\ActivityImportContext;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\DetermineActivityWeather;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Integration\Weather\OpenMeteo\OpenMeteo;
use App\Domain\Integration\Weather\OpenMeteo\OpenMeteoForecastApiCallHasFailed;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\MockObject\MockObject;

class DetermineActivityWeatherTest extends ContainerTestCase
{
    private DetermineActivityWeather $determineActivityWeather;
    private MockObject $openMeteo;

    public function testProcessWithoutActivityStartingCoordinate(): void
    {
        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed('1'),
            rawStravaData: [],
            isNewActivity: true,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withSportType(SportType::RIDE)
                    ->build()
            );

        $this->openMeteo
            ->expects($this->never())
            ->method('getWeatherStats');

        $this->determineActivityWeather->process($context);
    }

    public function testProcessWhenApiCallHasFailed(): void
    {
        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed('1'),
            rawStravaData: [],
            isNewActivity: true,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withSportType(SportType::RIDE)
                    ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('10'), Longitude::fromString('20')))
                    ->build()
            );

        $this->openMeteo
            ->expects($this->once())
            ->method('getWeatherStats')
            ->willThrowException(new OpenMeteoForecastApiCallHasFailed());

        $this->assertEquals(
            $context,
            $this->determineActivityWeather->process($context),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->determineActivityWeather = new DetermineActivityWeather(
            $this->openMeteo = $this->createMock(OpenMeteo::class),
        );
    }
}
