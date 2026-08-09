<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use Twig\Environment;

final readonly class ActivitySegmentsFragmentResolver implements FragmentResolver
{
    private const string BASE_PATH = 'activity';

    public function __construct(
        private ActivityRepository $activityRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (!preg_match('#^'.self::BASE_PATH.'/([^/]+)/segments$#', $path, $matches)) {
            return null;
        }

        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($matches[1]));
        } catch (EntityNotFound|\InvalidArgumentException) {
            return null;
        }

        return new ResolvedFragment(
            path: sprintf('%s/%s/segments', self::BASE_PATH, $activity->getId()),
            cacheability: Cacheability::none(),
            render: fn (): string => $this->twig->load('html/activity/_segments.html.twig')->render([
                'segmentEfforts' => $this->segmentEffortRepository->findByActivityId($activity->getId()),
                'sportType' => $activity->getSportType(),
            ]),
            type: FragmentType::PARTIAL,
        );
    }
}
