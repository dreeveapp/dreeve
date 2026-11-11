<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Segment;

use App\Domain\Segment\Search\SegmentSearchFilters;
use App\Domain\Segment\Search\SegmentSearchRepository;
use App\Infrastructure\Http\Request\PaginationFromRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class SegmentSearchRequestHandler
{
    use PaginationFromRequest;

    public function __construct(
        private SegmentSearchRepository $segmentSearchRepository,
    ) {
    }

    #[Route(path: '/api/v1/segments', name: 'api_v1_segments', methods: ['GET'], priority: 3)]
    public function handle(Request $request): SegmentResponse
    {
        $pagination = $this->paginationFromRequest($request);

        return SegmentResponse::list(
            overview: $this->segmentSearchRepository->find(
                pagination: $pagination,
                filters: SegmentSearchFilters::fromRequest($request),
            ),
            pagination: $pagination,
        );
    }
}
