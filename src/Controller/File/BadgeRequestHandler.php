<?php

declare(strict_types=1);

namespace App\Controller\File;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityTotals;
use App\Domain\Activity\BestEffort\BestEffortPeriod;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Domain\Activity\FindActivityTotals\FindActivityTotals;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Challenge\ChallengeRepository;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Zwift\ZwiftLevel;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\SvgResponse;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class BadgeRequestHandler
{
    private const int NUMBER_OF_MOST_RECENT_ACTIVITIES = 5;

    public function __construct(
        private SettingsRepository $settingsRepository,
        private ChallengeRepository $challengeRepository,
        private ActivityRepository $activityRepository,
        private BestEffortsCalculator $bestEffortsCalculator,
        private QueryBus $queryBus,
        private TranslatorInterface $translator,
        private Clock $clock,
        private CacheableRenderer $cacheableRenderer,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/badge/dreeve.svg', name: 'badge_dreeve', methods: ['GET'])]
    public function dreeve(): SvgResponse
    {
        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'badge.dreeve',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::CHALLENGES),
            ),
            render: fn (): string => $this->twig->load('svg/badge/svg-dreeve-badge.html.twig')->render([
                'athlete' => $this->settingsRepository->general()->getAthlete(),
                'activities' => $this->activityRepository->findMostRecent(self::NUMBER_OF_MOST_RECENT_ACTIVITIES),
                'activityTotals' => ActivityTotals::create(
                    totals: $this->queryBus->ask(new FindActivityTotals()),
                    now: $this->clock->getCurrentDateTimeImmutable(),
                    translator: $this->translator,
                ),
                'challengesCompleted' => $this->challengeRepository->count(),
            ]),
        );

        return new SvgResponse(
            $render->getContent() ?? '',
            headers: [...$render->getCacheHeaders(), 'Cache-Control' => 'no-cache, no-store, must-revalidate'],
        );
    }

    #[Route(path: '/badge/zwift.svg', name: 'badge_zwift', methods: ['GET'])]
    public function zwift(): SvgResponse
    {
        $zwiftLevel = $this->settingsRepository->zwift()->getZwiftLevel();
        if (!$zwiftLevel instanceof ZwiftLevel) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'badge.zwift',
                cacheTags: CacheTags::of(RootCacheTag::SETTINGS_ZWIFT),
            ),
            render: fn (): string => $this->twig->load('svg/badge/svg-zwift-badge.html.twig')->render([
                'athlete' => $this->settingsRepository->general()->getAthlete(),
                'zwiftLevel' => $zwiftLevel,
                'zwiftRacingScore' => $this->settingsRepository->zwift()->getZwiftRacingScore(),
            ]),
        );

        return new SvgResponse(
            $render->getContent() ?? '',
            headers: [...$render->getCacheHeaders(), 'Cache-Control' => 'no-cache, no-store, must-revalidate'],
        );
    }

    #[Route(path: '/badge/pb/{sportType}.svg', name: 'badge_personal_best', requirements: ['sportType' => '[a-z0-9\-]+'], methods: ['GET'])]
    public function personalBest(string $sportType): SvgResponse
    {
        $bestEfforts = $this->bestEffortsCalculator->calculate();

        $sportTypeWithBestEfforts = $bestEfforts->getAllSportTypesFor(BestEffortPeriod::ALL_TIME)->find(
            fn (SportType $sportTypeWithBestEfforts): bool => strtolower($sportTypeWithBestEfforts->value) === $sportType,
        );
        if (!$sportTypeWithBestEfforts instanceof SportType) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('badge.pb.%s', $sportType),
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ),
            render: fn (): string => $this->twig->load('svg/badge/svg-pb-badge.html.twig')->render([
                'sportType' => $sportTypeWithBestEfforts,
                'period' => BestEffortPeriod::ALL_TIME,
                'bestEfforts' => $bestEfforts,
            ]),
        );

        return new SvgResponse(
            $render->getContent() ?? '',
            headers: [...$render->getCacheHeaders(), 'Cache-Control' => 'no-cache, no-store, must-revalidate'],
        );
    }
}
