<?php

declare(strict_types=1);

namespace App\Domain\Badge;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityTotals;
use App\Domain\Activity\BestEffort\BestEffortPeriod;
use App\Domain\Activity\BestEffort\BestEfforts;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Domain\Activity\FindActivityTotals\FindActivityTotals;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Challenge\ChallengeRepository;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Zwift\ZwiftLevel;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BadgeFragmentResolver implements FragmentResolver
{
    private const string BASE_PATH = 'badge';
    private const int NUMBER_OF_MOST_RECENT_ACTIVITIES = 5;

    public function __construct(
        private SettingsRepository $settingsRepository,
        private ChallengeRepository $challengeRepository,
        private ActivityRepository $activityRepository,
        private BestEffortsCalculator $bestEffortsCalculator,
        private QueryBus $queryBus,
        private TranslatorInterface $translator,
        private Clock $clock,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (self::BASE_PATH.'/dreeve' === $path) {
            return $this->dreeveBadge();
        }

        if (self::BASE_PATH.'/zwift' === $path) {
            return $this->zwiftBadge();
        }

        if (preg_match('#^'.self::BASE_PATH.'/pb/([^/]+)$#', $path, $matches)) {
            return $this->personalBestBadge($matches[1]);
        }

        return null;
    }

    private function dreeveBadge(): ResolvedFragment
    {
        return new ResolvedFragment(
            path: self::BASE_PATH.'/dreeve',
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
            type: FragmentType::SVG,
        );
    }

    private function zwiftBadge(): ?ResolvedFragment
    {
        $zwiftLevel = $this->settingsRepository->zwift()->getZwiftLevel();
        if (!$zwiftLevel instanceof ZwiftLevel) {
            return null;
        }

        return new ResolvedFragment(
            path: self::BASE_PATH.'/zwift',
            cacheability: Cacheability::for(
                cacheKey: 'badge.zwift',
                cacheTags: CacheTags::of(RootCacheTag::SETTINGS_ZWIFT),
            ),
            render: fn (): string => $this->twig->load('svg/badge/svg-zwift-badge.html.twig')->render([
                'athlete' => $this->settingsRepository->general()->getAthlete(),
                'zwiftLevel' => $zwiftLevel,
                'zwiftRacingScore' => $this->settingsRepository->zwift()->getZwiftRacingScore(),
            ]),
            type: FragmentType::SVG,
        );
    }

    private function personalBestBadge(string $sportTypeFromPath): ?ResolvedFragment
    {
        $bestEfforts = $this->bestEffortsCalculator->calculate();

        $sportType = $this->findSportTypeWithBestEfforts($bestEfforts, $sportTypeFromPath);
        if (!$sportType instanceof SportType) {
            return null;
        }

        return new ResolvedFragment(
            path: sprintf('%s/pb/%s', self::BASE_PATH, $sportTypeFromPath),
            cacheability: Cacheability::for(
                cacheKey: sprintf('badge.pb.%s', $sportTypeFromPath),
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ),
            render: fn (): string => $this->twig->load('svg/badge/svg-pb-badge.html.twig')->render([
                'sportType' => $sportType,
                'period' => BestEffortPeriod::ALL_TIME,
                'bestEfforts' => $bestEfforts,
            ]),
            type: FragmentType::SVG,
        );
    }

    private function findSportTypeWithBestEfforts(BestEfforts $bestEfforts, string $sportTypeFromPath): ?SportType
    {
        foreach ($bestEfforts->getAllSportTypesFor(BestEffortPeriod::ALL_TIME) as $sportType) {
            if (strtolower($sportType->value) === $sportTypeFromPath) {
                return $sportType;
            }
        }

        return null;
    }
}
