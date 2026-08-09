<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use NeuronAI\Tools\Tool;

final class GetAllActivitiesSummary extends Tool
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
    ) {
        parent::__construct(
            'get_activities_summary',
            <<<DESC
            Retrieves a list of the user’s most recent 250 activities, sorted from newest to oldest, along with summary data for each activity.
            Use this tool whenever you need to identify which activities to include in a query or summary. 
            For example, when the user asks for insights like “Summarize my last week’s activities” or “Compare my last three rides.”
            This tool helps you determine which specific activities match the user’s time range or criteria, so you can use their IDs in subsequent tool calls.
            DESC
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $allActivities = $this->activityRepository->findAll();
        $summary = $allActivities
            ->slice(0, 250)
            ->map(fn (Activity $activity): array => [
                'id' => $activity->getId()->toUnprefixedString(),
                'on' => $activity->getStartDate()->format('Y-m-d'),
            ]);

        return [
            'allActivitiesSummary' => $summary,
            'totalActivityCount' => count($allActivities),
        ];
    }
}
