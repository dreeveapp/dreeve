<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Domain\Segment\FindEffortSummaryPerSegment\FindEffortSummaryPerSegment;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\DataTableRow;
use Twig\Environment;

final readonly class SegmentDataTableFragment implements Fragment
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private QueryBus $queryBus,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return SegmentFragmentPath::COLLECTION.'/data-table';
    }

    public function getType(): FragmentType
    {
        return FragmentType::DATA;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: SegmentFragmentPath::COLLECTION.'.data-table',
            cacheTags: CacheTags::of(RootCacheTag::SEGMENTS),
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

        return $dataTableRows;
    }
}
