<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentEffort\SegmentEffort;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

final class GetActivitySegmentEfforts extends Tool
{
    private const int MAX_RESULTS = 30;

    public function __construct(
        private readonly SegmentEffortRepository $segmentEffortRepository,
    ) {
        parent::__construct(
            'get_activity_segments_efforts',
            <<<DESC
            Retrieves detailed segment and segment effort information for a specific activity using its unique activity ID.
            Use this tool when the user asks about segments or segment efforts within an activity, or requests all details for a specific activity. 
            It requires the activity ID as input and provides the full segment-level data needed for analysis, comparisons, or summaries. 
            Example requests include “Show all segment efforts for activity 12345” or “Give me detailed segment stats for my last ride.”
            Returns at most 30 segment efforts. Compare "totalSegmentEffortCount" with the number of returned efforts: if it is higher, tell the user you are only showing some of them.
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
        $segmentEfforts = $this->segmentEffortRepository->findByActivityId($activityId);

        return [
            'segmentEfforts' => $segmentEfforts
                ->slice(0, self::MAX_RESULTS)
                ->map(fn (SegmentEffort $segmentEffort): array => $segmentEffort->exportForAITooling()),
            'totalSegmentEffortCount' => count($segmentEfforts),
        ];
    }
}
