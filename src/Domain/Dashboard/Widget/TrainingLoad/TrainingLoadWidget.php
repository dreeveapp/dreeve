<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Activity\DailyTrainingLoad;
use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Dashboard\Widget\TrainingLoad\FindNumberOfRestDays\FindNumberOfRestDays;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class TrainingLoadWidget implements Widget
{
    public function __construct(
        private ActivityHeartRateRepository $activityHeartRateRepository,
        private DailyTrainingLoad $dailyTrainingLoad,
        private QueryBus $queryBus,
        private FilesystemOperator $buildHtmlStorage,
        private Environment $twig,
        private TranslatorInterface $translator,
        private Clock $clock,
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

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $timeInHeartRateZonesForLast30Days = $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForLast30Days();

        $intensities = [];
        for ($i = (TrainingLoadChart::NUMBER_OF_DAYS_TO_DISPLAY + 210); $i >= 0; --$i) {
            $calculateForDate = $now->modify('- '.$i.' days');
            $intensities[$calculateForDate->format('Y-m-d')] = $this->dailyTrainingLoad->calculate($calculateForDate);
        }

        $trainingMetrics = TrainingMetrics::create($intensities);

        $numberOfRestDays = $this->queryBus->ask(new FindNumberOfRestDays(DateRange::fromDates(
            from: $now->modify('-6 days'),
            till: $now,
        )))->getNumberOfRestDays();

        $this->buildHtmlStorage->write(
            'training-load.html',
            $this->twig->render('html/dashboard/training-load.html.twig', [
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
                    now: $this->clock->getCurrentDateTimeImmutable()
                ),
                'restDaysInLast7Days' => $numberOfRestDays,
                'timeInHeartRateZonesForLast30Days' => $timeInHeartRateZonesForLast30Days,
            ])
        );

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'timeInHeartRateZonesForLast30Days' => $timeInHeartRateZonesForLast30Days,
            'trainingMetrics' => $trainingMetrics,
            'restDaysInLast7Days' => $numberOfRestDays,
        ]);
    }
}
