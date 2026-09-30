<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Activity;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsage;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsageRepository;
use App\Domain\Activity\Shifting\DrivetrainPosition;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ActivityShiftingRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityDrivetrainUsageRepository $activityDrivetrainUsageRepository,
        private CacheableRenderer $cacheableRenderer,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/internal/activities/{activityId}/shifting', name: 'activity_shifting', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'])]
    public function handle(string $activityId): Response
    {
        $activityId = ActivityId::fromString($activityId);

        if (!$this->activityRepository->exists($activityId)) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('activities.%s.shifting', $activityId->toUnprefixedString()),
                cacheTags: CacheTags::of(
                    ActivityCacheTag::for($activityId),
                    RootCacheTag::ACTIVITIES,
                ),
            ),
            render: fn (): string => $this->renderFor($activityId),
        );

        return new HtmlResponse($render->getContent() ?? '', headers: $render->getCacheHeaders());
    }

    private function renderFor(ActivityId $activityId): string
    {
        $drivetrainUsages = $this->activityDrivetrainUsageRepository->findByActivity($activityId);
        if ($drivetrainUsages->isEmpty()) {
            return '';
        }

        $distance = $this->activityRepository->find($activityId)->getDistanceInDisplayUnit();

        $rows = [];
        $shiftCounts = [];
        foreach (DrivetrainPosition::cases() as $position) {
            $drivetrainUsagesForPosition = $drivetrainUsages->filterOnPosition($position);
            $totalTimeInSeconds = (int) $drivetrainUsagesForPosition->sum(fn (ActivityDrivetrainUsage $drivetrainUsage): int => $drivetrainUsage->getTimeInSeconds());
            $shiftCounts[$position->value] = (int) $drivetrainUsagesForPosition->sum(fn (ActivityDrivetrainUsage $drivetrainUsage): int => $drivetrainUsage->getShiftCount());

            $rows[$position->value] = [];
            foreach ($drivetrainUsagesForPosition as $drivetrainUsage) {
                $rows[$position->value][] = [
                    'teeth' => $drivetrainUsage->getTeeth(),
                    'formattedTime' => $drivetrainUsage->getFormattedTime(),
                    'percentage' => $totalTimeInSeconds > 0 ? $drivetrainUsage->getTimeInSeconds() / $totalTimeInSeconds * 100 : 0.0,
                ];
            }
        }

        $frontShiftCount = $shiftCounts[DrivetrainPosition::FRONT->value];
        $rearShiftCount = $shiftCounts[DrivetrainPosition::REAR->value];

        return $this->twig->load('html/activity/_shifting.html.twig')->render([
            'frontRings' => $rows[DrivetrainPosition::FRONT->value],
            'rearCogs' => $rows[DrivetrainPosition::REAR->value],
            'frontShiftCount' => $frontShiftCount,
            'rearShiftCount' => $rearShiftCount,
            'frontShiftsPerDistanceUnit' => $distance->toFloat() > 0 ? $frontShiftCount / $distance->toFloat() : null,
            'rearShiftsPerDistanceUnit' => $distance->toFloat() > 0 ? $rearShiftCount / $distance->toFloat() : null,
            'distanceSymbol' => $distance->getSymbol(),
        ]);
    }
}
