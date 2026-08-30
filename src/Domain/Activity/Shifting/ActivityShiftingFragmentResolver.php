<?php

declare(strict_types=1);

namespace App\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityFragmentPath;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use Twig\Environment;

final readonly class ActivityShiftingFragmentResolver implements FragmentResolver
{
    private const string SUB_RESOURCE = 'shifting';

    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityDrivetrainUsageRepository $activityDrivetrainUsageRepository,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!$activityId = ActivityFragmentPath::match($path, self::SUB_RESOURCE)) {
            return null;
        }

        if (!$this->activityRepository->exists($activityId)) {
            return null;
        }

        return new ResolvedFragment(
            path: ActivityFragmentPath::for($activityId, self::SUB_RESOURCE),
            cacheability: Cacheability::for(
                cacheKey: ActivityFragmentPath::cacheKey($activityId, self::SUB_RESOURCE),
                cacheTags: CacheTags::of(
                    ActivityCacheTag::for($activityId),
                    RootCacheTag::ACTIVITIES,
                ),
            ),
            render: fn (): string => $this->renderFor($activityId),
            type: FragmentType::PARTIAL,
        );
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
