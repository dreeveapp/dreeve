<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
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
        private SegmentEffortRepository $segmentEffortRepository,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'segment/data-table';
    }

    public function getType(): FragmentType
    {
        return FragmentType::DATA;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: 'segment.data-table',
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

        do {
            $segments = $this->segmentRepository->findAll($pagination);
            /** @var Segment $segment */
            foreach ($segments as $segment) {
                $segmentEfforts = $this->segmentEffortRepository->findBySegmentId($segment->getId());
                $segment = $segment
                    ->withNumberOfTimesRidden(count($segmentEfforts))
                    ->withBestEffort($segmentEfforts->getBestEffort())
                    ->withLastEffortDate($segmentEfforts->getFirst()?->getStartDateTime());

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
