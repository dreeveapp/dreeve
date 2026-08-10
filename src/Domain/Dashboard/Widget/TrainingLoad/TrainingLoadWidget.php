<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Activity\DailyTrainingLoad;
use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\DependsOnCurrentDay;
use App\Domain\Dashboard\Widget\TrainingLoad\FindNumberOfRestDays\FindNumberOfRestDays;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class TrainingLoadWidget implements Widget, DependsOnCurrentDay
{
    public function __construct(
        private ActivityHeartRateRepository $activityHeartRateRepository,
        private DailyTrainingLoad $dailyTrainingLoad,
        private QueryBus $queryBus,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Training Load Analysis');
    }

    public function getTemplateName(): string
    {
        return 'widget--training-load';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::ACTIVITIES);
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $timeInHeartRateZonesForLast30Days = $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForLast30Days();

        $intensities = $this->dailyTrainingLoad->calculateForDateRange(DateRange::fromDates(
            from: $now->modify('- '.(TrainingLoadChart::NUMBER_OF_DAYS_TO_DISPLAY + 210).' days'),
            till: $now,
        ));

        $trainingMetrics = TrainingMetrics::create($intensities);

        $numberOfRestDays = $this->queryBus->ask(new FindNumberOfRestDays(DateRange::fromDates(
            from: $now->modify('-6 days'),
            till: $now,
        )))->getNumberOfRestDays();

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'timeInHeartRateZonesForLast30Days' => $timeInHeartRateZonesForLast30Days,
            'trainingMetrics' => $trainingMetrics,
            'restDaysInLast7Days' => $numberOfRestDays,
        ]);
    }
}
