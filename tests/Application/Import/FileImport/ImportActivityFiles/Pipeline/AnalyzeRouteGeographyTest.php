<?php

namespace App\Tests\Application\Import\FileImport\ImportActivityFiles\Pipeline;

use App\Application\Import\FileImport\ImportActivityFiles\Pipeline\ActivityImportContext;
use App\Application\Import\FileImport\ImportActivityFiles\Pipeline\AnalyzeRouteGeography;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorldType;
use App\Domain\Geography\Country\RouteGeographyAnalyzer;
use App\Domain\Integration\Geocoding\Nominatim\CouldNotReverseGeocodeAddress;
use App\Domain\Integration\Geocoding\Nominatim\Nominatim;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\String\Path;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\MockObject\MockObject;

class AnalyzeRouteGeographyTest extends ContainerTestCase
{
    private AnalyzeRouteGeography $analyzeRouteGeography;
    private MockObject $nominatim;

    public function testProcessWhenUnableToReverseGeocode(): void
    {
        $context = ActivityImportContext::create(Path::fromString('/lol/lol.png'))->withActivity(
            ActivityBuilder::fromDefaults()
                ->withSportType(SportType::RIDE)
                ->withRouteGeography(RouteGeography::create(['test' => 'lol']))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('10'), Longitude::fromString('20')))
                ->build()
        );

        $this->nominatim
            ->expects($this->once())
            ->method('reverseGeocode')
            ->willThrowException(new CouldNotReverseGeocodeAddress());

        $context = $this->analyzeRouteGeography->process($context);

        $this->assertEquals(
            RouteGeography::create(['test' => 'lol']),
            $context->getActivity()->getRouteGeography(),
        );
    }

    public function testProcessZwiftActivity(): void
    {
        $context = ActivityImportContext::create(Path::fromString('/lol/lol.png'))->withActivity(
            ActivityBuilder::fromDefaults()
                ->withSportType(SportType::VIRTUAL_RIDE)
                ->withRouteGeography(RouteGeography::create(['test' => 'lol']))
                ->withWorldType(WorldType::ZWIFT)
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('-11.636883'), Longitude::fromString('166.972044')))
                ->withPolyline('line')
                ->build()
        );

        $this->nominatim
            ->expects($this->never())
            ->method('reverseGeocode');

        $context = $this->analyzeRouteGeography->process($context);

        $this->assertEquals(
            RouteGeography::create(['test' => 'lol', 'state' => 'Watopia']),
            $context->getActivity()->getRouteGeography(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->analyzeRouteGeography = new AnalyzeRouteGeography(
            $this->nominatim = $this->createMock(Nominatim::class),
            $this->getContainer()->get(RouteGeographyAnalyzer::class),
        );
    }
}
