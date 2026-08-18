<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Stream\ActivityPowerRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\DataTableRow;
use Twig\Environment;

final readonly class ActivityDataTableFragment implements Fragment
{
    public function __construct(
        private EnrichedActivityRepository $enrichedActivityRepository,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'activities/data-table';
    }

    public function getType(): FragmentType
    {
        return FragmentType::DATA;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: 'activities.data-table',
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
        );
    }

    public function render(): string
    {
        return Json::encode($this->dataTableRows());
    }

    /**
     * @return DataTableRow[]
     */
    private function dataTableRows(): array
    {
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $rowTemplate = $this->twig->load('html/activity/activity-data-table-row.html.twig');

        $dataTableRows = [];
        foreach ($this->enrichedActivityRepository->findAll() as $enrichedActivity) {
            $activity = $enrichedActivity->getActivity();

            $dataTableRows[] = DataTableRow::create(
                markup: $rowTemplate->render([
                    'timeIntervals' => ActivityPowerRepository::TIME_INTERVALS_IN_SECONDS_REDACTED,
                    'activity' => $activity,
                    'enrichedActivity' => $enrichedActivity,
                ]),
                searchables: $activity->getSearchables(),
                filterables: $activity->getFilterables($unitSystem),
                sortValues: $enrichedActivity->getSortables(),
                summables: $activity->getSummables($unitSystem),
            );
        }

        return $dataTableRows;
    }
}
