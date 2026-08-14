<?php

namespace App\Tests\Domain\Activity\Route;

use App\Domain\Activity\Route\CountryGeographyAssets;
use App\Domain\Activity\Route\RouteGeographyAnalyzer;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class RouteGeographyAnalyzerTest extends TestCase
{
    private RouteGeographyAnalyzer $analyzer;

    public function testAnalyzeForPolylineCrossingTheCzechPolishBorder(): void
    {
        $this->assertEquals(
            ['CZ', 'PL'],
            $this->analyzer->analyzeForPolyline(EncodedPolyline::fromString(trim(
                file_get_contents(__DIR__.'/fixtures/route-pl-cz.txt') ?: ''
            )))
        );
    }

    public function testAnalyzeForPolyline(): void
    {
        $this->assertEquals(
            ['BE', 'NL'],
            $this->analyzer->analyzeForPolyline(EncodedPolyline::fromString(trim(
                file_get_contents(__DIR__.'/fixtures/route-be-nl.txt') ?: ''
            )))
        );
    }

    public function testAnalyzeForPolylineIsIndependentOfDirection(): void
    {
        $reversed = array_reverse(EncodedPolyline::fromString(trim(
            file_get_contents(__DIR__.'/fixtures/route-pl-cz.txt') ?: ''
        ))->decodeAndPairLatLng());

        $this->assertEquals(
            ['CZ', 'PL'],
            $this->analyzer->analyzeForPolyline(EncodedPolyline::fromCoordinates($reversed))
        );
    }

    public function testAnalyzeForPolylineThatOnlyTransitsACountryBetweenTwoCoordinates(): void
    {
        $this->assertEquals(
            ['CZ', 'PL'],
            $this->analyzer->analyzeForPolyline(EncodedPolyline::fromCoordinates([
                [50.28, 16.46],
                [50.32, 16.38],
            ]))
        );
    }

    public function testAnalyzeForPolylineInsideAnEnclave(): void
    {
        $this->assertEquals(
            ['LS'],
            $this->analyzer->analyzeForPolyline(EncodedPolyline::fromCoordinates([
                [-29.52, 28.61],
                [-29.29, 29.07],
            ]))
        );
    }

    public function testAnalyzeForPolylineThatStaysInOneCountry(): void
    {
        $this->assertEquals(
            ['PL'],
            $this->analyzer->analyzeForPolyline(EncodedPolyline::fromCoordinates([
                [52.2297, 21.0122],
                [52.2500, 21.0500],
                [52.2700, 21.0300],
            ]))
        );
    }

    #[TestWith([[[35.6762, 139.6503], [35.6800, 139.6600]], ['JP'], 'Tokyo'])]
    #[TestWith([[[-33.8688, 151.2093], [-33.8600, 151.2200]], ['AU'], 'Sydney'])]
    #[TestWith([[[39.7392, -104.9903], [39.7500, -104.9800]], ['US'], 'Denver'])]
    public function testAnalyzeForPolylineOutsideTheLongitudeBandOfLatitude(array $coordinates, array $expected, string $description): void
    {
        $this->assertEquals(
            $expected,
            $this->analyzer->analyzeForPolyline(EncodedPolyline::fromCoordinates($coordinates)),
            $description
        );
    }

    public function testAnalyzeForPolylineCrossingTheAntimeridian(): void
    {
        $this->assertEquals(
            ['FJ'],
            $this->analyzer->analyzeForPolyline(EncodedPolyline::fromCoordinates([
                [-16.98, 179.98],
                [-17.46, -179.14],
            ]))
        );
    }

    public function testAnalyzeForPolylineInTheMiddleOfTheOcean(): void
    {
        $this->assertEquals(
            [],
            $this->analyzer->analyzeForPolyline(EncodedPolyline::fromCoordinates([
                [0.0, -30.0],
                [0.1, -30.1],
            ]))
        );
    }

    public function testAnalyzeForInvalidPolyline(): void
    {
        $this->assertEquals(
            [],
            $this->analyzer->analyzeForPolyline(EncodedPolyline::fromString('}ftUqr{yH'))
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = new RouteGeographyAnalyzer(new CountryGeographyAssets());
    }
}
