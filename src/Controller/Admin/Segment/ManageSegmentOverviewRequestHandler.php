<?php

declare(strict_types=1);

namespace App\Controller\Admin\Segment;

use App\Domain\Import\ImportMode;
use App\Domain\Segment\Overview\SegmentOverviewFilters;
use App\Domain\Segment\Overview\SegmentOverviewRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Http\Request\PaginationFromRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageSegmentOverviewRequestHandler
{
    use PaginationFromRequest;

    public function __construct(
        private Environment $twig,
        private SegmentOverviewRepository $segmentOverviewRepository,
        private ImportMode $importMode,
    ) {
    }

    #[Route(path: '/admin/segments', name: 'admin_manage_segment_overview', methods: ['GET'], priority: 10)]
    public function handle(Request $request): HtmlResponse
    {
        if (!$this->importMode->isFiles()) {
            throw new NotFoundHttpException('Page not found');
        }

        $filters = SegmentOverviewFilters::fromRequest($request);

        return new HtmlResponse($this->twig->render('html/admin/page/segment/manage-segment-overview.html.twig', [
            'overview' => $this->segmentOverviewRepository->find(
                $this->paginationFromRequest($request),
                $filters,
            ),
            'filters' => $filters,
            'typeOptions' => $this->segmentOverviewRepository->countByType(SegmentType::IMPORTED) > 0
                ? [SegmentType::CUSTOM, SegmentType::IMPORTED]
                : [],
        ]));
    }
}
