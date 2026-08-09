<?php

declare(strict_types=1);

namespace App\Application\Build\BuildMonthlyStatsHtml;

use App\Domain\Activity\EnrichedActivities;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Calendar\Calendar;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStats;
use App\Domain\Calendar\Month;
use App\Domain\Calendar\Months;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use League\Flysystem\FilesystemOperator;
use Twig\Environment;

final readonly class BuildMonthlyStatsHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private SportTypeRepository $sportTypeRepository,
        private EnrichedActivities $enrichedActivities,
        private QueryBus $queryBus,
        private Environment $twig,
        private FilesystemOperator $buildHtmlStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildMonthlyStatsHtml);

        $now = $command->getCurrentDateTime();
        $allActivities = $this->enrichedActivities->findAll();

        $allMonths = Months::create(
            startDate: $allActivities->getFirstActivityStartDate(),
            endDate: $now
        );

        $monthlyStats = $this->queryBus->ask(new FindMonthlyStats());

        $this->buildHtmlStorage->write(
            'monthly-stats.html',
            $this->twig->load('html/calendar/monthly-stats.html.twig')->render([
                'monthlyStatistics' => $monthlyStats,
                'months' => $allMonths->reverse(),
                'sportTypes' => $this->sportTypeRepository->findAll(),
            ]),
        );

        /** @var Month $month */
        foreach ($allMonths as $month) {
            $this->buildHtmlStorage->write(
                'month/month-'.$month->getId().'.html',
                $this->twig->load('html/calendar/month.html.twig')->render([
                    'hasPreviousMonth' => $month->getId() != $allActivities->getFirstActivityStartDate()->format(Month::MONTH_ID_FORMAT),
                    'hasNextMonth' => $month->getId() != $now->format(Month::MONTH_ID_FORMAT),
                    'statistics' => $monthlyStats->getForMonth($month),
                    'calendar' => Calendar::create(
                        month: $month,
                        enrichedActivities: $this->enrichedActivities,
                    ),
                ]),
            );
        }
    }
}
