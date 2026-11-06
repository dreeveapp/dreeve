<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity;

use App\Domain\Activity\ActivityReference;
use App\Domain\Activity\Overview\ActivityOverviewItem;
use App\Domain\Activity\Overview\ActivityOverviewRepository;
use App\Domain\Settings\SettingsRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsController]
final readonly class SearchActivitiesRequestHandler
{
    private const int MAX_RESULTS = 10;

    public function __construct(
        private ActivityOverviewRepository $activityOverviewRepository,
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/admin/activities/search', name: 'admin_activity_search', methods: ['GET'], priority: 10)]
    public function handle(Request $request): JsonResponse
    {
        $query = trim(str_replace(['(', ')'], ' ', (string) $request->query->get('q', '')));
        if ('' === $query) {
            return new JsonResponse([]);
        }

        $dateFormat = (string) $this->settingsRepository->appearance()->getDateAndTimeFormat()->getDateFormatShort();

        return new JsonResponse(array_map(
            function (ActivityOverviewItem $item) use ($dateFormat): array {
                $date = $item->getStartDate()->translatedFormat($dateFormat);
                $sportType = $item->getSportType()->transSingular($this->translator);

                return [
                    'value' => (string) $item->getActivityId(),
                    'reference' => ActivityReference::format(
                        name: $item->getName(),
                        activityId: $item->getActivityId(),
                    ),
                    'label' => $item->getName(),
                    'sublabel' => sprintf('%s · %s', $date, $sportType),
                    'date' => $date,
                    'sportType' => $sportType,
                ];
            },
            $this->activityOverviewRepository->search(
                query: $query,
                limit: self::MAX_RESULTS
            ),
        ));
    }
}
