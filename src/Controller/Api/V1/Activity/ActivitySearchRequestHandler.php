<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Application\AppUrl;
use App\Domain\Activity\Search\ActivitySearchFilters;
use App\Domain\Activity\Search\ActivitySearchRepository;
use App\Domain\Activity\Search\ActivitySearchResult;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\Http\Request\PaginationFromRequest;
use App\Infrastructure\ValueObject\String\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivitySearchRequestHandler
{
    use PaginationFromRequest;

    public function __construct(
        private ActivitySearchRepository $activitySearchRepository,
        private MediaItemRepository $mediaItemRepository,
        private AppUrl $appUrl,
    ) {
    }

    #[Route(path: '/api/v1/activities', name: 'api_v1_activities', methods: ['GET'], priority: 3)]
    public function handle(Request $request): ActivityResponse
    {
        $pagination = $this->paginationFromRequest($request);
        $overview = $this->activitySearchRepository->find($pagination, ActivitySearchFilters::fromRequest($request));

        return ActivityResponse::list(
            overview: $overview,
            pagination: $pagination,
            mediaItems: $this->mediaItemRepository->findByIds(MediaItemIds::fromArray(array_merge(...array_map(
                static fn (ActivitySearchResult $result): array => array_map(
                    static fn (string $localImagePath): MediaItemId => MediaItemId::fromUnprefixed(Path::fromString($localImagePath)->getFilenameWithoutExtension()),
                    $result->getActivity()->getLocalImagePaths(),
                ),
                $overview->getItems(),
            )))),
            appUrl: $this->appUrl,
        );
    }
}
