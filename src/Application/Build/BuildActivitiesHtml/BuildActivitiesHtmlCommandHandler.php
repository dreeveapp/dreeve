<?php

declare(strict_types=1);

namespace App\Application\Build\BuildActivitiesHtml;

use App\Application\Countries;
use App\Domain\Activity\ActivityTotals;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Domain\Activity\CadenceDistributionChart;
use App\Domain\Activity\EnrichedActivities;
use App\Domain\Activity\HeartRateDistributionChart;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\LeafletMap;
use App\Domain\Activity\PowerDistributionChart;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Activity\Stream\ActivityPowerRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamProfileCharts;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Activity\VelocityDistributionChart;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\DataTableRow;
use App\Infrastructure\ValueObject\Measurement\Velocity\KmPerHour;
use App\Infrastructure\ValueObject\String\Slug;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BuildActivitiesHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private EnrichedActivities $enrichedActivities,
        private ActivityStreamRepository $activityStreamRepository,
        private ActivityStreamMetricRepository $activityStreamMetricRepository,
        private ActivityHeartRateRepository $activityHeartRateRepository,
        private CombinedActivityStreamRepository $combinedActivityStreamRepository,
        private ActivitySplitRepository $activitySplitRepository,
        private ActivityLapRepository $activityLapRepository,
        private SportTypeRepository $sportTypeRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private GearRepository $gearRepository,
        private RecordingDeviceRepository $recordingDeviceRepository,
        private BestEffortsCalculator $bestEffortsCalculator,
        private Countries $countries,
        private Environment $twig,
        private FilesystemOperator $buildHtmlStorage,
        private FilesystemOperator $buildApiStorage,
        private TranslatorInterface $translator,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildActivitiesHtml);

        $now = $command->getCurrentDateTime();
        $general = $this->settingsRepository->general();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $athlete = $general->getAthlete();
        $importedSportTypes = $this->sportTypeRepository->findAll();

        $activities = $this->enrichedActivities->findAll();

        $activityTotals = ActivityTotals::getInstance(
            activities: $activities,
            now: $now,
            translator: $this->translator,
        );

        $this->buildHtmlStorage->write(
            'activities.html',
            $this->twig->load('html/activity/activities.html.twig')->render([
                'sportTypes' => $importedSportTypes,
                'devices' => $this->recordingDeviceRepository->findAll(),
                'activityTotals' => $activityTotals,
                'countries' => $this->countries->getUsedInActivities(),
                'gears' => $this->gearRepository->findAllUsed(),
            ]),
        );

        $dataDatableRows = [];
        foreach ($activities as $activity) {
            $activityType = $activity->getSportType()->getActivityType();

            $valueDistributionMetrics = $this->activityStreamMetricRepository->findByActivityIdAndMetricType(
                $activity->getId(),
                ActivityStreamMetricType::VALUE_DISTRIBUTION
            );

            $distributionCharts = [];
            $heartRateDistribution = $valueDistributionMetrics->filterOnStreamType(StreamType::HEART_RATE)?->getData() ?? [];
            if ($activity->getAverageHeartRate() && [] !== $heartRateDistribution) {
                $distributionCharts[] = [
                    'title' => $this->translator->trans('Heart rate'),
                    'data' => Json::encode(HeartRateDistributionChart::create(
                        heartRateData: $heartRateDistribution,
                        averageHeartRate: $activity->getAverageHeartRate(),
                        athleteMaxHeartRate: $athlete->getMaxHeartRate($activity->getStartDate()),
                        heartRateZones: $general->getHeartRateZoneConfiguration()->getHeartRateZonesFor(
                            sportType: $activity->getSportType(),
                            on: $activity->getStartDate()
                        )
                    )->build()),
                ];
            }

            $powerDistribution = $valueDistributionMetrics->filterOnStreamType(StreamType::WATTS)?->getData() ?? [];
            if ($activityType->supportsPowerData() && $activity->getAveragePower()
                && count($powerDistribution) > 1) {
                $ftp = null;
                try {
                    $ftp = $general->getFtpHistory()->find(
                        activityType: $activityType,
                        on: $activity->getStartDate()
                    );
                } catch (EntityNotFound) {
                }

                $powerDistributionChart = PowerDistributionChart::create(
                    powerData: $powerDistribution,
                    averagePower: $activity->getAveragePower(),
                    ftp: $ftp,
                )->build();

                if (!is_null($powerDistributionChart)) {
                    $distributionCharts[] = [
                        'title' => $this->translator->trans('Power'),
                        'data' => Json::encode($powerDistributionChart),
                    ];
                }
            }

            $velocityDistribution = $valueDistributionMetrics->filterOnStreamType(StreamType::VELOCITY)?->getData() ?? [];
            if ([] !== $velocityDistribution) {
                $velocityUnitPreference = $activity->getSportType()->getVelocityDisplayPreference();

                $velocityDistributionChart = VelocityDistributionChart::create(
                    velocityData: $velocityDistribution,
                    averageSpeed: $activity->getAverageSpeed(),
                    sportType: $activity->getSportType(),
                    unitSystem: $unitSystem,
                )->build();

                if (!is_null($velocityDistributionChart)) {
                    $distributionCharts[] = [
                        'title' => match (true) {
                            $velocityUnitPreference instanceof KmPerHour => $this->translator->trans('Speed'),
                            default => $this->translator->trans('Pace'),
                        },
                        'data' => Json::encode($velocityDistributionChart),
                    ];
                }
            }

            $cadenceDistribution = $valueDistributionMetrics->filterOnStreamType(StreamType::CADENCE)?->getData() ?? [];
            if ($activity->getAverageCadence() && count($cadenceDistribution) > 1) {
                $cadenceDistributionChart = CadenceDistributionChart::create(
                    cadenceData: $cadenceDistribution,
                    averageCadence: $activity->getAverageCadence(),
                    activityType: $activityType,
                )->build();

                if (!is_null($cadenceDistributionChart)) {
                    $distributionCharts[] = [
                        'title' => $this->translator->trans('Cadence'),
                        'data' => Json::encode($cadenceDistributionChart),
                    ];
                }
            }

            $activitySplits = $this->activitySplitRepository->findBy(
                activityId: $activity->getId(),
                unitSystem: $unitSystem
            );

            $profileChart = null;
            $profileChartHeight = 0;
            $coordinateMap = [];
            try {
                $combinedActivityStream = $this->combinedActivityStreamRepository->findOneForActivityAndUnitSystem(
                    activityId: $activity->getId(),
                    unitSystem: $unitSystem
                );

                $maximumNumberOfDigits = $combinedActivityStream->getMaximumNumberOfDigits();
                $distances = $combinedActivityStream->getDistances();
                $times = $combinedActivityStream->getTimes();
                $grades = $combinedActivityStream->getGrades();
                $coordinateMap = $combinedActivityStream->getCoordinates();

                $streamTypesForCharts = $combinedActivityStream->getStreamTypesForCharts();
                $items = [];
                foreach ($streamTypesForCharts as $combinedStreamType) {
                    $items[] = [
                        'yAxisData' => $combinedActivityStream->getChartStreamData($combinedStreamType),
                        'yAxisStreamType' => $combinedStreamType,
                    ];
                }

                $combinedCharts = CombinedStreamProfileCharts::create(
                    items: array_reverse($items),
                    topXAxisData: $times,
                    bottomXAxisData: $distances,
                    bottomXAxisSuffix: $unitSystem->distanceSymbol(),
                    grades: $grades,
                    maximumNumberOfDigitsOnYAxis: $maximumNumberOfDigits,
                    unitSystem: $unitSystem,
                    translator: $this->translator,
                );
                $profileChart = $combinedCharts->build();
                $profileChartHeight = $combinedCharts->getTotalHeight();
            } catch (EntityNotFound) {
            }

            $unprefixedActivityId = $activity->getId()->toUnprefixedString();
            if ($profileChart) {
                $this->buildApiStorage->write(
                    sprintf('activity/%s/metrics.json', $unprefixedActivityId),
                    (string) Json::encodeAndCompress($profileChart),
                );
                $this->buildApiStorage->write(
                    sprintf('activity/%s/coordinates.json', $unprefixedActivityId),
                    (string) Json::encodeAndCompress($coordinateMap),
                );
            }

            $polylinesFileLocation = sprintf('activity/%s/polylines.json', $unprefixedActivityId);
            if (($leafletMap = $activity->getLeafletMap()) instanceof LeafletMap) {
                $coordinates = $coordinateMap ?: $activity->getEncodedPolyline()?->decodeAndPairLatLng();
                $this->buildApiStorage->write(
                    $polylinesFileLocation,
                    (string) Json::encodeAndCompress([$coordinates]),
                );
            }
            $templateName = sprintf('html/activity/%s.html.twig', $activity->getSportType()->getTemplateName());
            $gpxFileLocation = sprintf('api/activity/%s/route.gpx', $unprefixedActivityId);
            $activityHasTimeStream = $this->activityStreamRepository->hasOneForActivityAndStreamType($activity->getId(), StreamType::TIME);

            $timeInHeartRateZones = null;
            try {
                $timeInHeartRateZones = $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForActivity($activity->getId());
            } catch (EntityNotFound) {
            }

            $this->buildHtmlStorage->write(
                'activity/'.$activity->getId().'.html',
                $this->twig->load($templateName)->render([
                    'activity' => $activity,
                    'leaflet' => $leafletMap instanceof LeafletMap ? [
                        'polylineUrl' => $polylinesFileLocation,
                        'map' => $leafletMap,
                    ] : null,
                    'gpxLink' => $activityHasTimeStream ? $gpxFileLocation : null,
                    'gpxFileName' => sprintf(
                        '%s-%s.gpx',
                        $activity->getStartDate()->format('Y-m-d'),
                        Slug::fromString($activity->getName()),
                    ),
                    'distributionCharts' => $distributionCharts,
                    'segmentEfforts' => $this->segmentEffortRepository->findByActivityId($activity->getId()),
                    'splits' => $activitySplits,
                    'laps' => $this->activityLapRepository->findBy($activity->getId()),
                    'profileChartHeight' => $profileChartHeight,
                    'hasProfileChart' => null !== $profileChart,
                    'bestEfforts' => $this->bestEffortsCalculator->forActivity($activity->getId()),
                    'heartRateZones' => $timeInHeartRateZones,
                ]),
            );

            $dataDatableRows[] = DataTableRow::create(
                markup: $this->twig->load('html/activity/activity-data-table-row.html.twig')->render([
                    'timeIntervals' => ActivityPowerRepository::TIME_INTERVALS_IN_SECONDS_REDACTED,
                    'activity' => $activity,
                ]),
                searchables: $activity->getSearchables(),
                filterables: $activity->getFilterables($unitSystem),
                sortValues: $activity->getSortables(),
                summables: $activity->getSummables($unitSystem),
            );
        }

        $this->buildApiStorage->write(
            'activity/data-table.json',
            (string) Json::encodeAndCompress($dataDatableRows),
        );
    }
}
