<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Segment;

use App\Domain\Segment\FindEffortSummaryPerSegment\FindEffortSummaryPerSegment;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\DataTableRow;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class SegmentDataTableRequestHandler
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private QueryBus $queryBus,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/segments/data-table', name: 'segment_data_table', methods: ['GET'], priority: 3)]
    public function handle(): Response
    {
        $render = $this->cacheableRenderer->render(new ResolvedFragment(
            path: 'segments/data-table',
            cacheability: Cacheability::for(
                cacheKey: 'segments.data-table',
                cacheTags: CacheTags::of(RootCacheTag::SEGMENTS),
            ),
            render: fn (): string => $this->renderFor(),
        ));

        return new JsonResponse($render->getContent() ?? '[]', headers: $render->getCacheHeaders(), json: true);
    }

    private function renderFor(): string
    {
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $rowTemplate = $this->twig->load('html/segment/segment-data-table-row.html.twig');

        $dataTableRows = [];
        $pagination = Pagination::fromOffsetAndLimit(0, 100);
        $effortSummaries = $this->queryBus->ask(new FindEffortSummaryPerSegment());

        do {
            $segments = $this->segmentRepository->findAll($pagination);
            /** @var Segment $segment */
            foreach ($segments as $segment) {
                $summary = $effortSummaries->getForSegment($segment->getId());
                $segment = $segment
                    ->withNumberOfTimesRidden($summary?->getNumberOfTimesRidden() ?? 0)
                    ->withBestEffort($summary?->getBestEffort())
                    ->withLastEffortDate($summary?->getLastEffortDate());

                $dataTableRows[] = DataTableRow::create(
                    markup: $rowTemplate->render([
                        'segment' => $segment,
                    ]),
                    searchables: $segment->getSearchables(),
                    filterables: $segment->getFilterables($unitSystem),
                    sortValues: $segment->getSortables(),
                    summables: []
                );
            }

            $pagination = $pagination->next();
        } while (!$segments->isEmpty());

        return Json::encode($dataTableRows);
    }
}
