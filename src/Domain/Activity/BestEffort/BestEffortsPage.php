<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RootCacheTag;
use App\Infrastructure\Http\Page\Page;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BestEffortsPage implements Page
{
    public function __construct(
        private BestEffortsCalculator $bestEffortsCalculator,
        private TranslatorInterface $translator,
        private Clock $clock,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'best-efforts';
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            // Every period but "all time" is relative to the current date, so the render goes
            // stale at midnight, even when none of the activities changed.
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
                    )->build()
                );
            }
        }

        return $this->twig->load('html/best-efforts/best-efforts.html.twig')->render([
            'bestEffortsCharts' => $bestEffortsCharts,
            'bestEfforts' => $bestEfforts,
        ]);
    }
}
