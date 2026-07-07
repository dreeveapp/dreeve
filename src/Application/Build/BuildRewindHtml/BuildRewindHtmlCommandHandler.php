<?php

declare(strict_types=1);

namespace App\Application\Build\BuildRewindHtml;

use App\Domain\Activity\EnrichedActivities;
use App\Domain\Activity\Image\Image;
use App\Domain\Activity\Image\ImageRepository;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\FindMovingTimePerGear\FindMovingTimePerGear;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\MovingTimePerGearChart;
use App\Domain\Rewind\ActivityCountPerMonthChart;
use App\Domain\Rewind\ActivityLocationsChart;
use App\Domain\Rewind\ActivityStartTimesChart;
use App\Domain\Rewind\DailyActivitiesChart;
use App\Domain\Rewind\DistancePerMonthChart;
use App\Domain\Rewind\ElevationPerMonthChart;
use App\Domain\Rewind\FindActiveAndRestDays\FindActiveAndRestDays;
use App\Domain\Rewind\FindActivityCountPerMonth\FindActivityCountPerMonth;
use App\Domain\Rewind\FindActivityLocations\FindActivityLocations;
use App\Domain\Rewind\FindActivityStartTimesPerHour\FindActivityStartTimesPerHour;
use App\Domain\Rewind\FindAvailableRewindOptions\FindAvailableRewindOptions;
use App\Domain\Rewind\FindCaloriesBurnt\FindCaloriesBurnt;
use App\Domain\Rewind\FindCarbonSaved\FindCarbonSaved;
use App\Domain\Rewind\FindLongestActivity\FindLongestActivity;
use App\Domain\Rewind\FindMovingTimePerDay\FindMovingTimePerDay;
use App\Domain\Rewind\FindMovingTimePerSportType\FindMovingTimePerSportType;
use App\Domain\Rewind\FindStreaks\FindStreaks;
use App\Domain\Rewind\FindTotalActivityCount\FindTotalActivityCount;
use App\Domain\Rewind\FindTotalsPerMonth\FindTotalsPerMonth;
use App\Domain\Rewind\MovingTimePerMonthChart;
use App\Domain\Rewind\MovingTimePerSportTypeChart;
use App\Domain\Rewind\RestDaysVsActiveDaysChart;
use App\Domain\Rewind\RewindItem;
use App\Domain\Rewind\RewindItems;
use App\Domain\Rewind\RewindItemsPerGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BuildRewindHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private GearRepository $gearRepository,
        private ImageRepository $imageRepository,
        private EnrichedActivities $enrichedActivities,
        private QueryBus $queryBus,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private FilesystemOperator $buildHtmlStorage,
        private TranslatorInterface $translator,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildRewindHtml);

        $now = $command->getCurrentDateTime();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $availableRewindOptionsResponse = $this->queryBus->ask(new FindAvailableRewindOptions());
        $availableRewindOptions = $availableRewindOptionsResponse->getAvailableOptions();
        $usedGears = $this->gearRepository->findAllUsed();

        $rewindItemsPerYear = RewindItemsPerGroup::empty();
        foreach ($availableRewindOptions as $availableRewindOption) {
            $yearsToQuery = $availableRewindOptionsResponse->getYearsToQuery($availableRewindOption);
            $randomImage = null;
            try {
                $randomImage = $this->imageRepository->findRandomFor(
                    sportTypes: SportTypes::thatSupportImagesForStravaRewind(),
                    years: $yearsToQuery
                );
            } catch (EntityNotFound) {
            }

            $longestActivity = $this->queryBus->ask(new FindLongestActivity($yearsToQuery))->getActivity();
            $leafletMap = $longestActivity->getLeafletMap();

            $findMovingTimePerDayResponse = $this->queryBus->ask(new FindMovingTimePerDay($yearsToQuery));
            $findMovingTimePerSportTypeResponse = $this->queryBus->ask(new FindMovingTimePerSportType($yearsToQuery));
            $streaksResponse = $this->queryBus->ask(new FindStreaks($yearsToQuery, null));
            $totalsPerMonthResponse = $this->queryBus->ask(new FindTotalsPerMonth($yearsToQuery));
            $activeAndRestDaysResponse = $this->queryBus->ask(new FindActiveAndRestDays($yearsToQuery));
            $totalActivityCountResponse = $this->queryBus->ask(new FindTotalActivityCount($yearsToQuery));

            $rewindItems = RewindItems::empty();

            if (FindAvailableRewindOptions::ALL_TIME !== $availableRewindOption) {
                $rewindItems->add(RewindItem::from(
                    icon: 'calendar',
                    title: $this->translator->trans('Daily activities'),
                    subTitle: $this->translator->trans('{numberOfActivities} activities in {year}', [
                        '{numberOfActivities}' => $totalActivityCountResponse->getTotalActivityCount(),
                        '{year}' => $availableRewindOption,
                    ]),
                    content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                        'chart' => Json::encode(DailyActivitiesChart::create(
                            movingTimePerDay: $findMovingTimePerDayResponse->getMovingTimePerDay(),
                            year: Year::fromInt((int) $availableRewindOption),
                            translator: $this->translator,
                        )->build()),
                    ]),
                ));
            } else {
                $rewindItems->add(RewindItem::from(
                    icon: 'calendar',
                    title: $this->translator->trans('Daily activities'),
                    subTitle: $this->translator->trans('{numberOfActivities} activities', [
                        '{numberOfActivities}' => $totalActivityCountResponse->getTotalActivityCount(),
                    ]),
                    content: $this->twig->render('html/rewind/rewind-item-empty.html.twig', [
                        'message' => $this->translator->trans('Not supported'),
                    ]),
                    isPlaceHolderForComparison: true
                ));
            }

            $rewindItems
                ->add(RewindItem::from(
                    icon: 'rocket',
                    title: $this->translator->trans('Gear'),
                    subTitle: $this->translator->trans('Total hours spent per gear'),
                    content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                        'chart' => Json::encode(MovingTimePerGearChart::create(
                            movingTimePerGear: $this->queryBus->ask(new FindMovingTimePerGear($yearsToQuery, null))->getMovingTimePerGear(),
                            gears: $usedGears,
                        )->build()),
                    ]),
                ))
                ->add(RewindItem::from(
                    icon: 'trophy',
                    title: $this->translator->trans('Longest activity (h)'),
                    subTitle: $longestActivity->getName(),
                    content: $this->twig->render('html/rewind/rewind-biggest-activity.html.twig', [
                        'activity' => $longestActivity,
                        'leaflet' => $leafletMap ? [
                            'polylineUrl' => sprintf('activity/%s/polylines.json', $longestActivity->getId()->toUnprefixedString()),
                            'map' => $leafletMap,
                        ] : null,
                    ])
                ))
                ->add(RewindItem::from(
                    icon: 'distance',
                    title: $this->translator->trans('Distance'),
                    subTitle: $this->translator->trans('Total distance per month'),
                    content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                        'chart' => Json::encode(DistancePerMonthChart::create(
                            distancePerMonth: $totalsPerMonthResponse->getDistancePerMonth(),
                            unitSystem: $unitSystem,
                            translator: $this->translator,
                        )->build()),
                    ]),
                    totalMetric: $totalsPerMonthResponse->getTotalDistance()->toUnitSystem($unitSystem)->toInt(),
                    totalMetricLabel: $unitSystem->distanceSymbol(),
                ))
                ->add(RewindItem::from(
                    icon: 'time',
                    title: $this->translator->trans('Moving time'),
                    subTitle: $this->translator->trans('Total moving time per month'),
                    content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                        'chart' => Json::encode(MovingTimePerMonthChart::create(
                            movingTimePerMonth: $totalsPerMonthResponse->getMovingTimePerMonth(),
                            translator: $this->translator,
                        )->build()),
                    ]),
                    totalMetric: (int) round($totalsPerMonthResponse->getTotalMovingTime() / 3600),
                    totalMetricLabel: $this->translator->trans('hours'),
                ))
                ->add(RewindItem::from(
                    icon: 'elevation',
                    title: $this->translator->trans('Elevation'),
                    subTitle: $this->translator->trans('Total elevation per month'),
                    content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                        'chart' => Json::encode(ElevationPerMonthChart::create(
                            elevationPerMonth: $totalsPerMonthResponse->getElevationPerMonth(),
                            unitSystem: $unitSystem,
                            translator: $this->translator,
                        )->build()),
                    ]),
                    totalMetric: $totalsPerMonthResponse->getTotalElevation()->toUnitSystem($unitSystem)->toInt(),
                    totalMetricLabel: $unitSystem->elevationSymbol(),
                ))->add(RewindItem::from(
                    icon: 'time',
                    title: $this->translator->trans('Moving time by sport'),
                    subTitle: $this->translator->trans('Total hours spent per sport type'),
                    content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                        'chart' => Json::encode(MovingTimePerSportTypeChart::create(
                            movingTimePerSportType: $findMovingTimePerSportTypeResponse->getMovingTimePerSportType(),
                            translator: $this->translator,
                        )->build()),
                    ]),
                    totalMetric: (int) round($findMovingTimePerSportTypeResponse->getTotalMovingTime() / 3600),
                    totalMetricLabel: $this->translator->trans('hours')
                ))
                ->add(RewindItem::from(
                    icon: 'fire',
                    title: $this->translator->trans('Streaks'),
                    subTitle: $this->translator->trans('Longest streaks'),
                    content: $this->twig->render('html/rewind/rewind-streaks.html.twig', [
                        'dayStreak' => $streaksResponse->getLongestDayStreak(),
                        'weekStreak' => $streaksResponse->getLongestWeekStreak(),
                        'monthStreak' => $streaksResponse->getLongestMonthStreak(),
                    ])
                ))
                ->add(RewindItem::from(
                    icon: 'bed',
                    title: $this->translator->trans('Rest days'),
                    subTitle: $this->translator->trans('Rest days vs. active days'),
                    content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                        'chart' => Json::encode(RestDaysVsActiveDaysChart::create(
                            numberOfActiveDays: $activeAndRestDaysResponse->getNumberOfActiveDays(),
                            numberOfRestDays: $activeAndRestDaysResponse->getNumberOfRestDays(),
                            translator: $this->translator,
                        )->build()),
                    ]),
                    totalMetric: (int) round(($activeAndRestDaysResponse->getNumberOfActiveDays() / $activeAndRestDaysResponse->getTotalNumberOfDays()) * 100),
                    totalMetricLabel: '%'
                ))->add(RewindItem::from(
                    icon: 'clock',
                    title: $this->translator->trans('Start times'),
                    subTitle: $this->translator->trans('Activity start times'),
                    content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                        'chart' => Json::encode(ActivityStartTimesChart::create(
                            activityStartTimes: $this->queryBus->ask(new FindActivityStartTimesPerHour($yearsToQuery))->getActivityStartTimesPerHour(),
                            translator: $this->translator,
                        )->build()),
                    ]),
                ))
                ->add(RewindItem::from(
                    icon: 'hashtag',
                    title: $this->translator->trans('Activity count'),
                    subTitle: $this->translator->trans('Number of activities per month'),
                    content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                        'chart' => Json::encode(ActivityCountPerMonthChart::create(
                            activityCountPerMonth: $this->queryBus->ask(new FindActivityCountPerMonth($yearsToQuery))->getActivityCountPerMonth(),
                            translator: $this->translator,
                        )->build()),
                    ]),
                    totalMetric: $totalActivityCountResponse->getTotalActivityCount(),
                    totalMetricLabel: $this->translator->trans('activities'),
                ))
                ->add(RewindItem::from(
                    icon: 'carbon',
                    title: $this->translator->trans('Carbon saved'),
                    subTitle: $this->translator->trans('Reduced carbon emission by commuting'),
                    content: $this->twig->render('html/rewind/rewind-carbon-saved.html.twig', [
                        'kilogramCarbonSaved' => $this->queryBus->ask(new FindCarbonSaved($yearsToQuery))->getKgCoCarbonSaved(),
                    ]),
                    totalMetric: (int) round($this->queryBus->ask(new FindCarbonSaved(Years::all($now)))->getKgCoCarbonSaved()->toFloat()),
                    totalMetricLabel: 'kg CO₂',
                ))
                ->add(RewindItem::from(
                    icon: 'calories',
                    title: $this->translator->trans('Calories burnt'),
                    subTitle: $this->translator->trans('Energy burned across your activities'),
                    content: $this->twig->render('html/rewind/rewind-calories-burnt.html.twig', [
                        'calories' => $this->queryBus->ask(new FindCaloriesBurnt($yearsToQuery))->getCalories(),
                    ]),
                    totalMetric: $this->queryBus->ask(new FindCaloriesBurnt(Years::all($now)))->getCalories(),
                    totalMetricLabel: 'kcal',
                ));

            if ($activityLocations = $this->queryBus->ask(new FindActivityLocations($yearsToQuery))->getActivityLocations()) {
                $rewindItems->add(RewindItem::from(
                    icon: 'globe',
                    title: $this->translator->trans('Activity locations'),
                    subTitle: $this->translator->trans('Locations over the globe'),
                    content: $this->twig->render('html/rewind/rewind-chart-world-map.html.twig', [
                        'chart' => Json::encode(ActivityLocationsChart::create($activityLocations)->build()),
                    ]),
                ));
            } else {
                $rewindItems->add(RewindItem::from(
                    icon: 'globe',
                    title: $this->translator->trans('Activity locations'),
                    subTitle: $this->translator->trans('Locations over the globe'),
                    content: $this->twig->render('html/rewind/rewind-item-empty.html.twig', [
                        'message' => $this->translator->trans('No data available'),
                    ]),
                    isPlaceHolderForComparison: true
                ));
            }
            if ($randomImage instanceof Image) {
                $activity = $this->enrichedActivities->find($randomImage->getActivityId());
                $rewindItems->add(RewindItem::from(
                    icon: 'image',
                    title: $this->translator->trans('Photo'),
                    subTitle: $activity->getStartDate()->translatedFormat('M d, Y'),
                    content: $this->twig->render('html/rewind/rewind-random-image.html.twig', [
                        'image' => $randomImage,
                    ]),
                ));
            } else {
                $rewindItems->add(RewindItem::from(
                    icon: 'image',
                    title: $this->translator->trans('Photo'),
                    subTitle: '',
                    content: $this->twig->render('html/rewind/rewind-item-empty.html.twig', [
                        'message' => $this->translator->trans('No data available'),
                    ]),
                    isPlaceHolderForComparison: true
                ));
            }

            $rewindItemsPerYear->add(
                group: $availableRewindOption,
                items: $rewindItems,
            );

            $render = [
                'now' => $now,
                'availableRewindOptions' => $availableRewindOptions,
                'activeRewindOption' => $availableRewindOption,
                'rewindItems' => $rewindItems,
                'isAllTimeRewind' => FindAvailableRewindOptions::ALL_TIME === $availableRewindOption,
            ];

            $this->buildHtmlStorage->write(
                sprintf('rewind/%s.html', $availableRewindOption),
                $this->twig->load('html/rewind/rewind.html.twig')->render($render),
            );

            if ($availableRewindOptions[0] == $availableRewindOption) {
                $this->buildHtmlStorage->write(
                    'rewind.html',
                    $this->twig->load('html/rewind/rewind.html.twig')->render($render),
                );
            }
        }
        if (2 === count($availableRewindOptions)) {
            // "All time" and one other year are the only options. No need to compare rewinds.
            return;
        }

        foreach ($availableRewindOptions as $availableRewindOptionLeft) {
            $defaultRewindYearToCompareWith = $availableRewindOptions[0] != $availableRewindOptionLeft ? $availableRewindOptions[0] : $availableRewindOptions[1];

            foreach ($availableRewindOptions as $availableRewindOptionRight) {
                if ($availableRewindOptionLeft == $availableRewindOptionRight) {
                    continue;
                }

                $render = $this->twig->load('html/rewind/rewind-compare.html.twig')->render([
                    'availableRewindOptions' => $availableRewindOptions,
                    'availableRewindOptionsToCompareWith' => array_filter(
                        $availableRewindOptions,
                        fn (string $option): bool => $option != $availableRewindOptionLeft && $option != $availableRewindOptionRight,
                    ),
                    'activeRewindOptionLeft' => $availableRewindOptionLeft,
                    'activeRewindOptionRight' => $availableRewindOptionRight,
                    'rewindItemsLeft' => $rewindItemsPerYear->getForGroup($availableRewindOptionLeft),
                    'rewindItemsRight' => $rewindItemsPerYear->getForGroup($availableRewindOptionRight),
                    'rewindItemsLeftIsAllTimeRewind' => FindAvailableRewindOptions::ALL_TIME === $availableRewindOptionLeft,
                    'rewindItemsRightIsAllTimeRewind' => FindAvailableRewindOptions::ALL_TIME === $availableRewindOptionRight,
                ]);

                if ($availableRewindOptionRight == $defaultRewindYearToCompareWith) {
                    $this->buildHtmlStorage->write(
                        sprintf('rewind/%s/compare.html', $availableRewindOptionLeft),
                        $render
                    );
                }

                $this->buildHtmlStorage->write(
                    sprintf('rewind/%s/compare/%s.html', $availableRewindOptionLeft, $availableRewindOptionRight),
                    $render
                );
            }
        }
    }
}
