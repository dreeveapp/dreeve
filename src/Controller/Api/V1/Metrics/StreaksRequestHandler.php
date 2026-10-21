<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Metrics;

use App\Domain\Rewind\FindStreaks\FindStreaks;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\Years;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class StreaksRequestHandler
{
    public function __construct(
        private QueryBus $queryBus,
        private Clock $clock,
    ) {
    }

    #[Route(path: '/api/v1/metrics/streaks', name: 'api_v1_metrics_streaks', methods: ['GET'], priority: 3)]
    public function handle(Request $request): JsonResponse
    {
        $streaks = $this->queryBus->ask(new FindStreaks(
            years: Years::all($this->clock->getCurrentDateTimeImmutable()),
            restrictToSportTypes: MetricsFilters::fromRequest($request)->getSportTypes(),
        ));

        return new JsonResponse([
            'day' => [
                'current' => $streaks->getCurrentDayStreak(),
                'currentStartDate' => $streaks->getCurrentDayStreakStartDate()?->format('Y-m-d'),
                'longest' => $streaks->getLongestDayStreak(),
                'longestStartDate' => $streaks->getLongestDayStreakPeriod()?->getFrom()->format('Y-m-d'),
                'longestEndDate' => $streaks->getLongestDayStreakPeriod()?->getTill()->format('Y-m-d'),
            ],
            'week' => [
                'current' => $streaks->getCurrentWeekStreak(),
                'currentStartDate' => $streaks->getCurrentWeekStreakStartDate()?->format('Y-m-d'),
                'longest' => $streaks->getLongestWeekStreak(),
                'longestStartDate' => $streaks->getLongestWeekStreakPeriod()?->getFrom()->format('Y-m-d'),
                'longestEndDate' => $streaks->getLongestWeekStreakPeriod()?->getTill()->format('Y-m-d'),
            ],
            'month' => [
                'current' => $streaks->getCurrentMonthStreak(),
                'currentStartDate' => $streaks->getCurrentMonthStreakStartDate()?->format('Y-m-d'),
                'longest' => $streaks->getLongestMonthStreak(),
                'longestStartDate' => $streaks->getLongestMonthStreakPeriod()?->getFrom()->format('Y-m-d'),
                'longestEndDate' => $streaks->getLongestMonthStreakPeriod()?->getTill()->format('Y-m-d'),
            ],
        ]);
    }
}
