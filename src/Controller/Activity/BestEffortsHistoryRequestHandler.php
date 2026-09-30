<?php

declare(strict_types=1);

namespace App\Controller\Activity;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\BestEffort\BestEffortPeriod;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableContent;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Measurement\Length\ConvertableToMeter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class BestEffortsHistoryRequestHandler
{
    private const string BASE_PATH = 'best-efforts';

    public function __construct(
        private BestEffortsCalculator $bestEffortsCalculator,
        private ActivityRepository $activityRepository,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/best-efforts/{activityType}/{distanceInMeter}', name: 'best_efforts_history', requirements: ['activityType' => '[^/]+', 'distanceInMeter' => '\d+'], methods: ['GET'], priority: 3)]
    public function handle(string $activityType, string $distanceInMeter): Response
    {
        if (!$activityType = ActivityType::tryFrom($activityType)) {
            throw new NotFoundHttpException('Not found');
        }

        $distanceInMeter = (int) $distanceInMeter;
        $distance = array_find(
            $activityType->getDistancesForBestEffortCalculation(),
            fn (ConvertableToMeter $distance): bool => $distance->toMeter()->toInt() === $distanceInMeter
        );
        if (!$distance instanceof ConvertableToMeter) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(new CacheableContent(
            cacheability: Cacheability::for(
                cacheKey: sprintf('%s.%s.%d', self::BASE_PATH, $activityType->value, $distanceInMeter),
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ),
            render: fn (): string => $this->renderFor($activityType, $distance),
        ));

        return new HtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: NavigationSection::BEST_EFFORTS,
                openGraph: null,
            ),
            headers: $render->getCacheHeaders(),
        );
    }

    private function renderFor(ActivityType $activityType, ConvertableToMeter $distance): string
    {
        $bestEfforts = $this->bestEffortsCalculator->calculate();

        return $this->twig->load('html/best-efforts/best-efforts-history.html.twig')->render([
            'activityType' => $activityType,
            'period' => BestEffortPeriod::ALL_TIME,
            'distance' => $distance,
            'bestEfforts' => $bestEfforts,
            'activitiesPerActivityId' => $this->activityRepository->findByIds($bestEfforts->getActivityIds())->keyByActivityId(),
        ]);
    }
}
