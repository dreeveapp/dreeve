<?php

declare(strict_types=1);

namespace App\Application\Build\BuildGearStatsHtml;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Domain\Activity\EnrichedActivities;
use App\Domain\Calendar\Months;
use App\Domain\Gear\DistanceOverTimePerGearChart;
use App\Domain\Gear\DistancePerMonthPerGearChart;
use App\Domain\Gear\FindGearStatsPerDay\FindGearStatsPerDay;
use App\Domain\Gear\Gear;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\GearType;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BuildGearStatsHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private GearRepository $gearRepository,
        private MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private EnrichedActivities $enrichedActivities,
        private SettingsRepository $settingsRepository,
        private QueryBus $queryBus,
        private Environment $twig,
        private FilesystemOperator $buildHtmlStorage,
        private TranslatorInterface $translator,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildGearStatsHtml);

        $now = $command->getCurrentDateTime();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $activities = $this->enrichedActivities->findAll();
        $allUsedGear = $this->gearRepository->findAllUsed();
        $gearStats = $this->queryBus->ask(new FindGearStatsPerDay());
        $allMonths = Months::create(
            startDate: $activities->getFirstActivityStartDate(),
            endDate: $now
        );

        $activeGear = $allUsedGear->filter(fn (Gear $gear): bool => !$gear->isRetired());
        $unspecifiedGear = $this->buildUnspecifiedGear($activities);
        if ($unspecifiedGear instanceof Gear) {
            $activeGear->add($unspecifiedGear);
        }

        $this->buildHtmlStorage->write(
            'gear.html',
            $this->twig->load('html/gear/gear.html.twig')->render([
                'maintenanceTaskIsDue' => !$this->maintenanceTaskProgressCalculator->getGearIdsThatHaveDueTasks()->isEmpty(),
                'activeGear' => $activeGear,
                'retiredGear' => $allUsedGear->filter(fn (Gear $gear): bool => $gear->isRetired()),
                'unitSystem' => $unitSystem,
                'distancePerMonthPerGearChart' => Json::encode(
                    DistancePerMonthPerGearChart::create(
                        gearCollection: $allUsedGear,
                        activityCollection: $activities,
                        unitSystem: $unitSystem,
                        months: $allMonths,
                    )->build()
                ),
                'distanceOverTimePerGear' => Json::encode(
                    DistanceOverTimePerGearChart::create(
                        gears: $allUsedGear,
                        gearStats: $gearStats,
                        startDate: $activities->getFirstActivityStartDate(),
                        unitSystem: $unitSystem,
                        translator: $this->translator,
                        now: $now,
                    )->build()
                ),
            ]),
        );
    }

    private function buildUnspecifiedGear(Activities $activities): ?Gear
    {
        $activitiesWithoutGear = $activities->filter(fn (Activity $activity): bool => !$activity->getGearId() instanceof GearId);
        $count = count($activitiesWithoutGear);

        if (0 === $count) {
            return null;
        }

        $distanceInMeter = Meter::from($activitiesWithoutGear->sum(fn (Activity $activity): float => $activity->getDistance()->toMeter()->toFloat()));
        $movingTimeInSeconds = (int) $activitiesWithoutGear->sum(fn (Activity $activity): int => $activity->getMovingTimeInSeconds());
        $elevation = Meter::from($activitiesWithoutGear->sum(fn (Activity $activity): float => $activity->getElevation()->toFloat()));
        $totalCalories = (int) $activitiesWithoutGear->sum(fn (Activity $activity): ?int => $activity->getCalories());

        return Gear::fromState(
            gearId: GearId::none(),
            distanceInMeter: $distanceInMeter,
            createdOn: SerializableDateTime::fromString('1970-01-01'),
            name: 'Unspecified',
            isRetired: false,
            type: GearType::IMPORTED,
            localImagePath: null,
            movingTime: Seconds::from($movingTimeInSeconds),
            elevation: $elevation,
            numberOfActivities: $count,
            totalCalories: $totalCalories,
            purchasePrice: null,
        );
    }
}
