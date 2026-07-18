<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity;

use App\Domain\Activity\ActivityOverviewItem;
use App\Domain\Activity\ActivityOverviewRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class SearchActivitiesRequestHandler
{
    private const int MAX_RESULTS = 10;

    public function __construct(
        private ActivityOverviewRepository $activityOverviewRepository,
    ) {
    }

    #[Route(path: '/admin/activities/search', name: 'admin_activity_search', methods: ['GET'], priority: 10)]
    public function handle(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if ('' === $query) {
            return new JsonResponse([]);
        }

        return new JsonResponse(array_map(
            static fn (ActivityOverviewItem $item): array => [
                'value' => $item->getActivityId()->toUnprefixedString(),
                'label' => (string) $item->getName(),
                'sublabel' => sprintf(
                    '%s · %s',
                    $item->getStartDate()->format('Y-m-d H:i'),
                    $item->getSportType()->value
                ),
            ],
            $this->activityOverviewRepository->search(
                query: $query,
                limit: self::MAX_RESULTS
            ),
        ));
    }
}
