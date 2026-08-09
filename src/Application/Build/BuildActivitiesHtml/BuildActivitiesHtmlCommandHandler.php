<?php

declare(strict_types=1);

namespace App\Application\Build\BuildActivitiesHtml;

use App\Application\Countries;
use App\Domain\Activity\ActivityTotals;
use App\Domain\Activity\EnrichedActivities;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Activity\Stream\ActivityPowerRepository;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\DataTableRow;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BuildActivitiesHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private EnrichedActivities $enrichedActivities,
        private SportTypeRepository $sportTypeRepository,
        private GearRepository $gearRepository,
        private RecordingDeviceRepository $recordingDeviceRepository,
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
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
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
