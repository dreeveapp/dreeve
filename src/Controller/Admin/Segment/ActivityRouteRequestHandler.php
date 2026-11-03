<?php

declare(strict_types=1);

namespace App\Controller\Admin\Segment;

use App\Domain\Activity\ActivityReference;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Import\ImportMode;
use App\Domain\Segment\AddCustomSegment\AddSegmentElevationChart;
use App\Domain\Settings\SettingsRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityRouteRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private ImportMode $importMode,
        private SettingsRepository $settingsRepository,
    ) {
    }

    #[Route(path: '/admin/activities/route', name: 'admin_activity_route', methods: ['GET'], priority: 10)]
    public function handle(Request $request): JsonResponse
    {
        if (!$this->importMode->isFiles()) {
            throw new NotFoundHttpException('Page not found');
        }

        try {
            $activityId = ActivityReference::parse((string) $request->query->get('activityId', ''));
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Activity not found');
        }
        if (!$this->activityRepository->exists($activityId)) {
            throw new NotFoundHttpException('Activity not found');
        }

        $streams = $this->activityStreamRepository->findByActivityId($activityId);
        $points = array_values($streams->filterOnType(StreamType::LAT_LNG)?->getData() ?? []);
        $distance = array_values($streams->filterOnType(StreamType::DISTANCE)?->getData() ?? []);
        $altitude = array_values($streams->filterOnType(StreamType::ALTITUDE)?->getData() ?? []);

        if ([] === $points || [] === $distance) {
            throw new NotFoundHttpException('Activity has no route');
        }

        $numberOfPoints = min(count($points), count($distance));
        $distance = array_slice($distance, 0, $numberOfPoints);
        $altitude = count($altitude) >= $numberOfPoints ? array_slice($altitude, 0, $numberOfPoints) : [];

        return new JsonResponse([
            'points' => array_slice($points, 0, $numberOfPoints),
            'distance' => $distance,
            'altitude' => $altitude,
            'chart' => [] === $altitude ? null : AddSegmentElevationChart::create(
                distances: $distance,
                altitudes: $altitude,
                unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
            )->build(),
        ]);
    }
}
