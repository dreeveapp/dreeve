<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Activity;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityFragmentPath;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsage;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsageRepository;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsages;
use App\Domain\Activity\Shifting\DrivetrainPosition;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ActivityShiftingRequestHandler
{
    private const string SUB_RESOURCE = 'shifting';

    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityDrivetrainUsageRepository $activityDrivetrainUsageRepository,
        private CacheableRenderer $cacheableRenderer,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/internal/activities/{activityId}/shifting', name: 'activity_shifting', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'], priority: 3)]
    public function handle(string $activityId): Response
    {
        $activityId = ActivityId::fromString($activityId);

        if (!$this->activityRepository->exists($activityId)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $render = $this->cacheableRenderer->render(new ResolvedFragment(
            path: ActivityFragmentPath::for($activityId, self::SUB_RESOURCE),
            cacheability: Cacheability::for(
                cacheKey: ActivityFragmentPath::cacheKey($activityId, self::SUB_RESOURCE),
                cacheTags: CacheTags::of(
                    ActivityCacheTag::for($activityId),
                    RootCacheTag::ACTIVITIES,
                ),
            ),
            render: fn (): string => $this->renderFor($activityId),
        ));

        return new HtmlResponse($render->getContent() ?? '', headers: $render->getCacheHeaders());
    }

    private function renderFor(ActivityId $activityId): string
    {
        $drivetrainUsages = $this->activityDrivetrainUsageRepository->findByActivity($activityId);
        if ($drivetrainUsages->isEmpty()) {
            return '';
        }

        $distance = $this->activityRepository->find($activityId)->getDistanceInDisplayUnit();
        $frontShiftCount = $this->countShifts($drivetrainUsages, DrivetrainPosition::FRONT);
        $rearShiftCount = $this->countShifts($drivetrainUsages, DrivetrainPosition::REAR);

        return $this->twig->load('html/activity/_shifting.html.twig')->render([
            'frontRings' => $this->buildRows($drivetrainUsages->filterOnPosition(DrivetrainPosition::FRONT)),
            'rearCogs' => $this->buildRows($drivetrainUsages->filterOnPosition(DrivetrainPosition::REAR)),
            'frontShiftCount' => $frontShiftCount,
            'rearShiftCount' => $rearShiftCount,
            'frontShiftsPerDistanceUnit' => $distance->toFloat() > 0 ? $frontShiftCount / $distance->toFloat() : null,
            'rearShiftsPerDistanceUnit' => $distance->toFloat() > 0 ? $rearShiftCount / $distance->toFloat() : null,
            'distanceSymbol' => $distance->getSymbol(),
        ]);
    }

    /**
     * @return list<array{teeth: int, formattedTime: string, percentage: float}>
     */
    private function buildRows(ActivityDrivetrainUsages $drivetrainUsages): array
    {
        if ($drivetrainUsages->isEmpty()) {
            return [];
        }

        $totalTimeInSeconds = (int) $drivetrainUsages->sum(fn (ActivityDrivetrainUsage $drivetrainUsage): int => $drivetrainUsage->getTimeInSeconds());

        $rows = [];
        foreach ($drivetrainUsages as $drivetrainUsage) {
            $rows[] = [
                'teeth' => $drivetrainUsage->getTeeth(),
                'formattedTime' => $drivetrainUsage->getFormattedTime(),
                'percentage' => $totalTimeInSeconds > 0 ? $drivetrainUsage->getTimeInSeconds() / $totalTimeInSeconds * 100 : 0.0,
            ];
        }

        return $rows;
    }

    private function countShifts(ActivityDrivetrainUsages $drivetrainUsages, DrivetrainPosition $position): int
    {
        return (int) $drivetrainUsages
            ->filterOnPosition($position)
            ->sum(fn (ActivityDrivetrainUsage $drivetrainUsage): int => $drivetrainUsage->getShiftCount());
    }
}
