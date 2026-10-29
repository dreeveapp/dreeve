<?php

declare(strict_types=1);

namespace App\Controller\Page\BestEffort;

use App\Application\Navigation\NavigationSection;
use App\Application\PageMetadata\PageMetadata;
use App\Application\PageRenderer;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Measurement\Length\ConvertableToMeter;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class BestEffortsHistoryRequestHandler
{
    private const string BASE_PATH = 'best-efforts';

    public function __construct(
        private BestEffortsCalculator $bestEffortsCalculator,
        private ActivityRepository $activityRepository,
        private PageRenderer $pageRenderer,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/best-efforts/{activityType}/{distanceInMeter}', name: 'best_efforts_history', requirements: ['activityType' => '[^/]+', 'distanceInMeter' => '\d+'], methods: ['GET'])]
    public function handle(string $activityType, string $distanceInMeter): HtmlResponse
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

        $distanceLabel = sprintf('%s %s', $distance->isLowerThanOne() ? round($distance->toFloat(), 1) : $distance->toInt(), $distance->getSymbol());

        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('%s.%s.%d', self::BASE_PATH, $activityType->value, $distanceInMeter),
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ),
            render: fn (): string => $this->renderFor($activityType, $distance),
            navigationSection: NavigationSection::BEST_EFFORTS,
            metadata: new PageMetadata(
                title: sprintf('%s - %s - %s', $this->translator->trans('Best efforts', domain: 'metadata'), $activityType->trans($this->translator), $distanceLabel),
            ),
        );
    }

    private function renderFor(ActivityType $activityType, ConvertableToMeter $distance): string
    {
        $history = $this->bestEffortsCalculator->calculateHistory(
            activityType: $activityType,
            distance: $distance
        );

        return $this->twig->load('html/best-efforts/best-efforts-history.html.twig')->render([
            'activityType' => $activityType,
            'distance' => $distance,
            'history' => $history,
            'activitiesPerActivityId' => $this->activityRepository->findByIds($history->getActivityIds())->keyByActivityId(),
        ]);
    }
}
