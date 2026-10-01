<?php

declare(strict_types=1);

namespace App\Controller\Page\Dashboard;

use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Activity\DailyTrainingLoad;
use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Dashboard\Widget\TrainingLoad\FindNumberOfRestDays\FindNumberOfRestDays;
use App\Domain\Dashboard\Widget\TrainingLoad\PolarisedTraining;
use App\Domain\Dashboard\Widget\TrainingLoad\TrainingLoadChart;
use App\Domain\Dashboard\Widget\TrainingLoad\TrainingLoadForecastProjection;
use App\Domain\Dashboard\Widget\TrainingLoad\TrainingMetrics;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\DateRange;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class TrainingLoadRequestHandler
{
    public function __construct(
        private ActivityHeartRateRepository $activityHeartRateRepository,
        private DailyTrainingLoad $dailyTrainingLoad,
        private QueryBus $queryBus,
        private TranslatorInterface $translator,
        private Clock $clock,
        private Environment $twig,
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/dashboard/training-load', name: 'dashboard_training_load', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'dashboard.training-load',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
                ttlInSeconds: $this->clock->getCurrentDateTimeImmutable()->getSecondsUntilMidnight(),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::DASHBOARD,
        );
    }

    private function renderFor(): string
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
            'polarisedTraining' => PolarisedTraining::fromRollingWindow(
                $this->activityHeartRateRepository->findTimeInHeartRateZonesForLast30Days()
            ),
        ]);
    }
}
