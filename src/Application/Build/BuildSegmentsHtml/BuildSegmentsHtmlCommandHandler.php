<?php

declare(strict_types=1);

namespace App\Application\Build\BuildSegmentsHtml;

use App\Application\Countries;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentEffort\SegmentEffortHistoryChart;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortVsHeartRateChart;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\DataTableRow;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BuildSegmentsHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private SportTypeRepository $sportTypeRepository,
        private Countries $countries,
        private Environment $twig,
        private FilesystemOperator $buildHtmlStorage,
        private FilesystemOperator $buildApiStorage,
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildSegmentsHtml);

        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $importedSportTypes = $this->sportTypeRepository->findAll();

        $dataDatableRows = [];
        $pagination = Pagination::fromOffsetAndLimit(0, 100);

        do {
            $segments = $this->segmentRepository->findAll($pagination);
            /** @var Segment $segment */
            foreach ($segments as $segment) {
                $segmentEffortsTopTen = $this->segmentEffortRepository->findTopXBySegmentId($segment->getId(), 10);
                $segmentEfforts = $this->segmentEffortRepository->findBySegmentId($segment->getId());
                $segment = $segment
                    ->withNumberOfTimesRidden($this->segmentEffortRepository->countBySegmentId($segment->getId()))
                    ->withBestEffort($segmentEffortsTopTen->getBestEffort())
                    ->withLastEffortDate($segmentEfforts->getFirst()?->getStartDateTime());

                $polylinesFileLocation = sprintf('segment/%s/polylines.json', $segment->getId()->toUnprefixedString());
                if (($leafletMap = $segment->getLeafletMap()) && !$this->buildApiStorage->fileExists($polylinesFileLocation)) {
                    $this->buildApiStorage->write(
                        $polylinesFileLocation,
                        (string) Json::encodeAndCompress([$segment->getPolyline()?->decodeAndPairLatLng()]),
                    );
                }

                $this->buildHtmlStorage->write(
                    'segment/'.$segment->getId().'.html',
                    $this->twig->load('html/segment/segment.html.twig')->render([
                        'segment' => $segment,
                        'segmentEffortsTopTen' => $segmentEffortsTopTen,
                        'segmentEffortsVsHeartRateChart' => Json::encode(
                            SegmentEffortVsHeartRateChart::create(
                                segmentEfforts: $segmentEfforts,
                                sportType: $segment->getSportType(),
                                unitSystem: $unitSystem,
                                translator: $this->translator
                            )->build()
                        ),
                        'segmentEffortsHistoryChart' => Json::encode(
                            SegmentEffortHistoryChart::create($segmentEfforts)->build()
                        ),
                        'leaflet' => $leafletMap ? [
                            'polylineUrl' => $polylinesFileLocation,
                            'map' => $leafletMap,
                        ] : null,
                    ]),
                );

                $dataDatableRows[] = DataTableRow::create(
                    markup: $this->twig->load('html/segment/segment-data-table-row.html.twig')->render([
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

        $this->buildApiStorage->write(
            'segment/data-table.json',
            (string) Json::encodeAndCompress($dataDatableRows),
        );

        $this->buildHtmlStorage->write(
            'segments.html',
            $this->twig->load('html/segment/segments.html.twig')->render([
                'sportTypes' => $importedSportTypes,
                'countries' => $this->countries->getUsedInSegments(),
                'totalSegmentCount' => $this->segmentRepository->count(),
            ]),
        );
    }
}
