<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Theme\Theme;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BestEffortsFragment implements Fragment
{
    public function __construct(
        private BestEffortsCalculator $bestEffortsCalculator,
        private ActivityRepository $activityRepository,
        private TranslatorInterface $translator,
        private Clock $clock,
        private Environment $twig,
        private Theme $theme,
    ) {
    }

    public function getPath(): string
    {
        return 'best-efforts';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ttlInSeconds: $this->clock->getCurrentDateTimeImmutable()->getSecondsUntilMidnight(),
        );
    }

    public function render(): string
    {
        $bestEfforts = $this->bestEffortsCalculator->calculate();
        $bestEffortsCharts = [];

        foreach ($bestEfforts->getActivityTypes() as $activityType) {
            foreach (BestEffortPeriod::cases() as $bestEffortPeriod) {
                $bestEffortsCharts[$activityType->value][$bestEffortPeriod->value] = Json::encode(
                    BestEffortChart::create(
                        activityType: $activityType,
                        period: $bestEffortPeriod,
                        bestEfforts: $bestEfforts,
                        translator: $this->translator,
                        theme: $this->theme,
                    )->build()
                );
            }
        }

        return $this->twig->load('html/best-efforts/best-efforts.html.twig')->render([
            'bestEffortsCharts' => $bestEffortsCharts,
            'bestEfforts' => $bestEfforts,
            'activitiesPerActivityId' => $this->activityRepository->findByIds($bestEfforts->getActivityIds())->keyByActivityId(),
        ]);
    }
}
