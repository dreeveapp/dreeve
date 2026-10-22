<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Dashboard\Widget\TrainingGoals\FindTrainingGoalMetrics\FindTrainingGoalMetrics;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;

final readonly class GetTrainingVolume implements AITool
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function getName(): string
    {
        return 'get_training_volume';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves the total training volume between two dates: number of activities, moving time, distance, elevation and calories.
            Optionally restrict it to one or more sport types.
            Use this tool when the user asks how much they trained in a period, for example “How far did I ride in March?” or “How many hours did I run this year?”
            DESC;
    }

    public function getParameters(): array
    {
        return [
            ToolParameter::string('from', 'The first day to include (YYYY-MM-DD).', required: true),
            ToolParameter::string('to', 'The last day to include (YYYY-MM-DD).', required: true),
            ToolParameter::string('sportType', 'Comma separated Strava sport types, for example "Ride,VirtualRide". Leave empty for all sport types.'),
        ];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        $from = $arguments->getDate('from') ?? throw new ToolFailed('"from" is required.');
        $to = $arguments->getDate('to') ?? throw new ToolFailed('"to" is required.');
        if ($from->isAfter($to)) {
            throw new ToolFailed('"from" must not be later than "to".');
        }

        $trainingVolume = $this->queryBus->ask(new FindTrainingGoalMetrics(
            sportTypes: $arguments->getSportTypes(),
            from: $from,
            to: $to,
        ));

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'numberOfActivities' => $trainingVolume->getNumberOfActivities()->toInt(),
            'movingTimeInSeconds' => $trainingVolume->getMovingTime()->toInt(),
            'distanceInMeter' => $trainingVolume->getDistance()->toMeter()->toInt(),
            'elevationInMeter' => $trainingVolume->getElevation()->toInt(),
            'calories' => $trainingVolume->getCalories()->toInt(),
        ];
    }
}
