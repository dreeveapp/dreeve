<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Activity\DailyTrainingLoad;
use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Dashboard\Widget\TrainingLoad\FindNumberOfRestDays\FindNumberOfRestDays;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\DateRange;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class TrainingLoadFragment implements Fragment
{
    public function __construct(
        private ActivityHeartRateRepository $activityHeartRateRepository,
        private DailyTrainingLoad $dailyTrainingLoad,
        private QueryBus $queryBus,
        private TranslatorInterface $translator,
        private Clock $clock,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'training-load';
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
        $now = $this->clock->getCurrentDateTimeImmutable();

        $trainingMetrics = TrainingMetrics::create($this->dailyTrainingLoad->calculateForDateRange(DateRange::fromDates(
            from: $now->modify('- '.(TrainingLoadChart::NUMBER_OF_DAYS_TO_DISPLAY + 210).' days'),
            till: $now,
        )));

        $numberOfRestDays = $this->queryBus->ask(new FindNumberOfRestDays(DateRange::fromDates(
            from: $now->modify('-6 days'),
            till: $now,
        )))->getNumberOfRestDays();

        return $this->twig->load('html/dashboard/training-load.html.twig')->render([
            'trainingLoadChart' => Json::encode(
                TrainingLoadChart::create(
                    trainingMetrics: $trainingMetrics,
                    now: $now,
                    translator: $this->translator,
                )->build()
            ),
            'trainingMetrics' => $trainingMetrics,
            'trainingLoadForecast' => TrainingLoadForecastProjection::create(
                metrics: $trainingMetrics,
                now: $now,
            ),
            'restDaysInLast7Days' => $numberOfRestDays,
            'timeInHeartRateZonesForLast30Days' => $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForLast30Days(),
        ]);
    }
}
