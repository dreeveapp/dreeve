<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Domain\Activity\Search\ActivitySearchRepository;
use App\Infrastructure\Http\Request\PaginationFromRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivitySearchRequestHandler
{
    use PaginationFromRequest;

    public function __construct(
        private ActivitySearchRepository $activitySearchRepository,
    ) {
    }

    #[Route(path: '/api/v1/activities', name: 'api_v1_activities', methods: ['GET'], priority: 3)]
    public function handle(Request $request): ActivityResponse
    {
        $pagination = $this->paginationFromRequest($request);

        return ActivityResponse::list(
            $this->activitySearchRepository->find($pagination, ActivitySearchFilters::fromRequest($request)),
            $pagination,
        );
    }
}
