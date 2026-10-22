<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Segment\SegmentEffort\SegmentEffort;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;

final readonly class GetActivitySegmentEfforts implements AITool
{
    private const int MAX_RESULTS = 30;

    public function __construct(
        private SegmentEffortRepository $segmentEffortRepository,
    ) {
    }

    public function getName(): string
    {
        return 'get_activity_segments_efforts';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves detailed segment and segment effort information for a specific activity using its unique activity ID.
            Use this tool when the user asks about segments or segment efforts within an activity, or requests all details for a specific activity.
            It requires the activity ID as input and provides the full segment-level data needed for analysis, comparisons, or summaries.
            Example requests include “Show all segment efforts for activity 12345” or “Give me detailed segment stats for my last ride.”
            Returns at most 30 segment efforts. Compare "totalSegmentEffortCount" with the number of returned efforts: if it is higher, tell the user you are only showing some of them.
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
        $segmentEfforts = $this->segmentEffortRepository->findByActivityId($arguments->getActivityId());

        return [
            'segmentEfforts' => $segmentEfforts
                ->slice(0, self::MAX_RESULTS)
                ->map(static fn (SegmentEffort $segmentEffort): array => $segmentEffort->exportForAITooling()),
            'totalSegmentEffortCount' => count($segmentEfforts),
        ];
    }
}
