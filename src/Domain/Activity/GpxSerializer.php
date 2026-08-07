<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Serialization\Escape;

// Based on https://github.com/SauceLLC/sauce4strava/blob/3548ad2346a23aaf275a965190a6e013dd21e111/src/common/export.mjs#L67
final readonly class GpxSerializer
{
    private const string DATE_TIME_FORMAT = 'Y-m-d\TH:i:s.000\Z';

    public function __construct(
        private EnrichedActivities $enrichedActivities,
        private ActivityStreamRepository $activityStreamRepository,
    ) {
    }

    public function serialize(ActivityId $activityId): ?string
    {
        $activity = $this->enrichedActivities->find($activityId);
        $activitySteams = $this->activityStreamRepository->findByActivityId($activity->getId());

        if (!$timeStream = $activitySteams->filterOnType(StreamType::TIME)) {
            return null;
        }
        $latLngStream = $activitySteams->filterOnType(StreamType::LAT_LNG)?->getData() ?? [];
        $altitudeStream = $activitySteams->filterOnType(StreamType::ALTITUDE)?->getData() ?? [];
        $powerStream = $activitySteams->filterOnType(StreamType::WATTS)?->getData() ?? [];
        $heartRateStream = $activitySteams->filterOnType(StreamType::HEART_RATE)?->getData() ?? [];
        $cadenceStream = $activitySteams->filterOnType(StreamType::CADENCE)?->getData() ?? [];
        $temperatureStream = $activitySteams->filterOnType(StreamType::TEMP)?->getData() ?? [];

        $rootNode = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><gpx/>');
        $rootNode->addAttribute('creator', 'Strava Statitics');
        $rootNode->addAttribute('version', '1.1');
        $rootNode->addAttribute('xmlns', 'http://www.topografix.com/GPX/1/1');
        $rootNode->addAttribute('xmlns:xmlns:gpx3', 'http://www.garmin.com/xmlschemas/GpxExtensions/v3');
        $rootNode->addAttribute('xmlns:xmlns:tpx1', 'http://www.garmin.com/xmlschemas/TrackPointExtension/v1');
        $rootNode->addAttribute('xmlns:xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->addAttribute('xmlns:xsi:schemaLocation',
            implode(' ', [
                'http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd',
                'http://www.garmin.com/xmlschemas/GpxExtensions/v3 '.
                'https://www8.garmin.com/xmlschemas/GpxExtensionsv3.xsd',
                'http://www.garmin.com/xmlschemas/TrackPointExtension/v1 '.
                'https://www8.garmin.com/xmlschemas/TrackPointExtensionv1.xsd',
            ])
        );

        $metadataNode = $rootNode->addChild('metadata');
        $metadataNode->addChild('time', $activity->getStartDate()->format(self::DATE_TIME_FORMAT));

        $trkNode = $rootNode->addChild('trk');
        $trkNode->addChild('name', Escape::forJsonEncode($activity->getName()));
        $trkNode->addChild('type', $activity->getSportType()->value);
        $description = $activity->getDescription();
        if ('' !== $description && '0' !== $description) {
            $trkNode->addChild('desc', Escape::forJsonEncode($description));
        }
        $trksegNode = $trkNode->addChild('trkseg');

        foreach ($timeStream->getData() as $i => $time) {
            $trkptNode = $trksegNode->addChild('trkpt');
            if (isset($latLngStream[$i])) {
                $trkptNode->addAttribute('lat', (string) $latLngStream[$i][0]);
                $trkptNode->addAttribute('lon', (string) $latLngStream[$i][1]);
            }

            $intervalInSeconds = \DateInterval::createFromDateString($time.' seconds');
            $trkptNode->addChild(
                'time',
                $activity->getStartDate()->add($intervalInSeconds)->format(self::DATE_TIME_FORMAT)
            );

            if (isset($altitudeStream[$i])) {
                $trkptNode->addChild('ele', (string) $altitudeStream[$i]);
            }
            $extensionsNode = $trkptNode->addChild('extensions');
            if (isset($powerStream[$i])) {
                $extensionsNode->addChild('power', (string) $powerStream[$i]);
            }

            if (isset($temperatureStream[$i]) || isset($heartRateStream[$i]) || isset($cadenceStream[$i])) {
                $tpxNode = $extensionsNode->addChild('xmlns:tpx1:TrackPointExtension');
                if (isset($temperatureStream[$i])) {
                    $tpxNode->addChild('xmlns:tpx1:atemp', (string) $temperatureStream[$i]);
                }
                if (isset($heartRateStream[$i])) {
                    $tpxNode->addChild('xmlns:tpx1:hr', (string) $heartRateStream[$i]);
                }
                if (isset($cadenceStream[$i])) {
                    $tpxNode->addChild('xmlns:tpx1:cad', (string) $cadenceStream[$i]);
                }
            }
        }

        if (!$xml = $rootNode->asXML()) {
            return null;
        }

        return $xml;
    }
}
