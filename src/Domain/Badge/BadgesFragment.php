<?php

declare(strict_types=1);

namespace App\Domain\Badge;

use App\Application\AppUrl;
use App\Domain\Activity\BestEffort\BestEffortPeriod;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use Twig\Environment;

final readonly class BadgesFragment implements Fragment
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private BestEffortsCalculator $bestEffortsCalculator,
        private AppUrl $appUrl,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'badges';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::SETTINGS_ZWIFT),
        );
    }

    public function render(): string
    {
        return $this->twig->load('html/badges.html.twig')->render([
            'zwiftLevel' => $this->settingsRepository->zwift()->getZwiftLevel(),
            'appUrl' => rtrim((string) $this->appUrl, '/'),
            'sportTypesThatHaveBestEfforts' => $this->bestEffortsCalculator->calculate()->getAllSportTypesFor(BestEffortPeriod::ALL_TIME),
        ]);
    }
}
