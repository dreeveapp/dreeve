<?php

declare(strict_types=1);

namespace App\Controller\Api\Activity;

use App\Domain\Activity\EnrichedActivityRepository;
use App\Domain\Activity\Stream\ActivityPowerRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\Cache\RootCacheTag;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\DataTableRow;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ActivityDataTableApiRequestHandler
{
    private const string CACHE_KEY = 'activity.data-table';

    public function __construct(
        private EnrichedActivityRepository $enrichedActivityRepository,
        private SettingsRepository $settingsRepository,
        private RenderCache $renderCache,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/activity/data-table.json', name: 'api_activity_data_table', methods: ['GET'], priority: 3)]
    public function handle(): JsonResponse
    {
        $render = $this->renderCache->get(
            cacheKey: self::CACHE_KEY,
            cacheability: Cacheability::for(
                cacheKey: self::CACHE_KEY,
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ),
            callback: fn (): string => Json::encode($this->dataTableRows()),
        );

        $response = new JsonResponse($render->getContent() ?? '[]', json: true);
        $response->headers->add($render->getCacheHeaders());

        return $response;
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
