<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\BestEffort\BestEffortPeriod;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Measurement\Length\ConvertableToMeter;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class BestEffortsApiRequestHandler
{
    public function __construct(
        private BestEffortsCalculator $bestEffortsCalculator,
        private RenderCache $renderCache,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/best-efforts/{activityType}/{distanceInMeter}', name: 'api_best_efforts_history', requirements: ['distanceInMeter' => '\d+'], methods: ['GET'], priority: 3)]
    public function history(string $activityType, string $distanceInMeter): HtmlResponse
    {
        if (!$type = ActivityType::tryFrom($activityType)) {
            throw new NotFoundHttpException(sprintf('Activity type "%s" not found', $activityType));
        }

        $distanceInMeter = (int) $distanceInMeter;
        $distance = array_find(
            $type->getDistancesForBestEffortCalculation(),
            fn (ConvertableToMeter $distance): bool => $distance->toMeter()->toInt() === $distanceInMeter
        );
        if (!$distance instanceof ConvertableToMeter) {
            throw new NotFoundHttpException(sprintf('Best efforts for "%s" over %d meter not found', $type->value, $distanceInMeter));
        }

        $cacheKey = sprintf('best-efforts.%s.%d', $type->value, $distanceInMeter);
        $render = $this->renderCache->get(
            cacheKey: $cacheKey,
            cacheability: Cacheability::for(
                cacheKey: $cacheKey,
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ),
            callback: fn (): string => $this->twig->load('html/best-efforts/best-efforts-history.html.twig')->render([
                'activityType' => $type,
                'period' => BestEffortPeriod::ALL_TIME,
                'distance' => $distance,
                'bestEfforts' => $this->bestEffortsCalculator->calculate(),
            ]),
        );

        $response = new HtmlResponse($render->getContent() ?? '');
        $response->headers->add($render->getCacheHeaders());

        return $response;
    }
}
