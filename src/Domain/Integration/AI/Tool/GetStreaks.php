<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Rewind\FindStreaks\FindStreaks;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\Years;

final readonly class GetStreaks implements AITool
{
    public function __construct(
        private QueryBus $queryBus,
        private Clock $clock,
    ) {
    }

    public function getName(): string
    {
        return 'get_streaks';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves the athlete’s current and longest streaks of consecutive days, weeks and months with at least one activity, including when they started and ended.
            Optionally restrict it to one or more sport types.
            Use this tool when the user asks about consistency, for example “What is my longest running streak?”
            DESC;
    }

    public function getParameters(): array
    {
        return [
            ToolParameter::string('sportType', 'Comma separated Strava sport types, for example "Run,TrailRun". Leave empty for all sport types.'),
        ];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        $streaks = $this->queryBus->ask(new FindStreaks(
            years: Years::all($this->clock->getCurrentDateTimeImmutable()),
            restrictToSportTypes: $arguments->getSportTypes(),
        ));

        return [
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
        ];
    }
}
