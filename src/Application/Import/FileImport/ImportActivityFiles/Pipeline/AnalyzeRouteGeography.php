<?php

declare(strict_types=1);

namespace App\Application\Import\FileImport\ImportActivityFiles\Pipeline;

use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\Route\RouteGeographyAnalyzer;
use App\Domain\Integration\Geocoding\Nominatim\CouldNotReverseGeocodeAddress;
use App\Domain\Integration\Geocoding\Nominatim\Nominatim;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 90)]
final readonly class AnalyzeRouteGeography implements ImportActivityFileStep
{
    public function __construct(
        private Nominatim $nominatim,
        private RouteGeographyAnalyzer $routeGeographyAnalyzer,
    ) {
    }

    public function process(ActivityImportContext $context): ActivityImportContext
    {
        $activity = $context->getActivity() ?? throw new \RuntimeException('Activity not set on $context');
        $sportType = $activity->getSportType();

        $routeGeography = $activity->getRouteGeography();
        if (!$routeGeography->isReversedGeocoded() && $activity->getStartingCoordinate() instanceof \App\Infrastructure\ValueObject\Geography\Coordinate) {
            if ($sportType->supportsReverseGeocoding()) {
                try {
                    $routeGeography = $routeGeography->updateWith(
                        $this->nominatim->reverseGeocode($activity->getStartingCoordinate())
                    );
                } catch (CouldNotReverseGeocodeAddress) {
                }
            } elseif ($activity->isZwiftRide() && ($zwiftMap = $activity->getLeafletMap())) {
                $routeGeography = $routeGeography->updateWith([
                    'state' => $zwiftMap->getLabel(),
                ]);
            }
        }

        if (!$activity->getRouteGeography()->hasBeenAnalyzedForRouteGeography()
            && $sportType->supportsReverseGeocoding() && $activity->getEncodedPolyline() instanceof \App\Infrastructure\ValueObject\Geography\EncodedPolyline) {
            $routeGeography = $routeGeography->updateWith([
                RouteGeography::PASSED_TROUGH_COUNTRIES => $this->routeGeographyAnalyzer->analyzeForPolyline(
                    $activity->getEncodedPolyline()
                ),
            ]);
        }

        return $context->withActivity($activity->withRouteGeography($routeGeography));
    }
}
