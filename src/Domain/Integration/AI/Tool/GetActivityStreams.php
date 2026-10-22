<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\Stream\ActivityStream;
use App\Domain\Activity\Stream\ActivityStreamRepository;

final readonly class GetActivityStreams implements AITool
{
    public function __construct(
        private ActivityStreamRepository $activityStreamRepository,
    ) {
    }

    public function getName(): string
    {
        return 'get_activity_streams';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves aggregated stream statistics for a specific activity using its unique activity ID.
            Use this tool when the user asks about heart rate, power or speed ranges within an activity.
            It requires the activity ID as input and returns, per available stream, the number of recorded points plus the minimum, maximum and average value.
            It does NOT return the raw time-series, so it cannot be used to analyse how a value evolved over the course of the activity.
            Example requests include “What was my average and max power on activity 12345?”
            DESC;
    }

    public function getParameters(): array
    {
        return [
            ToolParameter::string('activityId', 'The id of the activity.', required: true),
        ];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        return $this->activityStreamRepository
            ->findByActivityId($arguments->getActivityId())
            ->map(static fn (ActivityStream $stream): array => $stream->exportForAITooling());
    }
}
