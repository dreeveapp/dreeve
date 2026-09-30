<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Activity;

use App\Domain\Activity\EnrichedActivityRepository;
use App\Domain\Activity\Stream\ActivityPowerRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\DataTableRow;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ActivityDataTableRequestHandler
{
    public function __construct(
        private EnrichedActivityRepository $enrichedActivityRepository,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/activities/data-table', name: 'activity_data_table', methods: ['GET'])]
    public function handle(): Response
    {
        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'activities.data-table',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ),
            render: fn (): string => $this->renderFor(),
        );

        return new JsonResponse($render->getContent() ?? '[]', headers: $render->getCacheHeaders(), json: true);
    }

    private function renderFor(): string
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

        return Json::encode($dataTableRows);
    }
}
