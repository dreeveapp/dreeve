<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\Lap\ActivityLap;
use App\Domain\Activity\Lap\ActivityLapRepository;

final readonly class GetActivityLaps implements AITool
{
    private const int MAX_RESULTS = 50;

    public function __construct(
        private ActivityLapRepository $activityLapRepository,
    ) {
    }

    public function getName(): string
    {
        return 'get_activity_laps';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves detailed lap information for a specific activity using its unique activity ID.
            Use this tool when the user asks about lap data within an activity or requests all details for a specific activity.
            It requires the activity ID as input and provides the lap-by-lap breakdown needed for summaries, analysis, or comparisons.
            Example requests include “Show all laps for activity 12345” or “Give me detailed summary of activity 12345.”
            Returns at most 50 laps, from the start of the activity. Compare "totalLapCount" with the number of returned laps: if it is higher, tell the user you are only seeing part of the activity.
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
        $laps = $this->activityLapRepository->findBy($arguments->getActivityId());

        return [
            'laps' => $laps
                ->slice(0, self::MAX_RESULTS)
                ->map(static fn (ActivityLap $lap): array => $lap->exportForAITooling()),
            'totalLapCount' => count($laps),
        ];
    }
}
