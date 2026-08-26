<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\ActivityStream;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

final class GetActivityStreams extends Tool
{
    public function __construct(
        private readonly ActivityStreamRepository $activityStreamRepository,
    ) {
        parent::__construct(
            'get_activity_streams',
            <<<DESC
            Retrieves aggregated stream statistics for a specific activity using its unique activity ID.
            Use this tool when the user asks about heart rate, power or speed ranges within an activity.
            It requires the activity ID as input and returns, per available stream, the number of recorded points plus the minimum, maximum and average value.
            It does NOT return the raw time-series, so it cannot be used to analyse how a value evolved over the course of the activity.
            Example requests include “What was my average and max power on activity 12345?”
            DESC
        );
    }

    /**
     * @return \NeuronAI\Tools\ToolPropertyInterface[]
     *
     * @codeCoverageIgnore
     */
    #[\Override]
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'activityId',
                type: PropertyType::STRING,
                description: 'The id of the activity.',
                required: true
            ),
        ];
    }

    #[\Override]
    public function getMaxRuns(): int
    {
        return 25;
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(string $activityId): array
    {
        $activityId = ActivityId::fromUnprefixed($activityId);
        $streams = $this->activityStreamRepository->findByActivityId($activityId);

        return $streams->map(static fn (ActivityStream $stream): array => $stream->exportForAITooling());
    }
}
