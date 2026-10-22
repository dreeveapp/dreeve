<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\Split\ActivitySplit;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Settings\SettingsRepository;

final readonly class GetActivitySplits implements AITool
{
    private const int MAX_RESULTS = 60;

    public function __construct(
        private ActivitySplitRepository $activitySplitRepository,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function getName(): string
    {
        return 'get_activity_splits';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves detailed split information for a specific activity using its unique activity ID.
            Use this tool when the user asks about split data within an activity or requests all details for a specific activity.
            It requires the activity ID as input and provides the split-by-split breakdown needed for summaries, analysis, or comparisons.
            Example requests include “Show all splits for activity 12345” or “Give me detailed split stats for my last run.”
            Returns at most 60 splits, from the start of the activity. Compare "totalSplitCount" with the number of returned splits: if it is higher, tell the user you are only seeing part of the activity.
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
        $splits = $this->activitySplitRepository->findBy(
            activityId: $arguments->getActivityId(),
            unitSystem: $this->settingsRepository->appearance()->getUnitSystem()
        );

        return [
            'splits' => $splits
                ->slice(0, self::MAX_RESULTS)
                ->map(static fn (ActivitySplit $split): array => $split->exportForAITooling()),
            'totalSplitCount' => count($splits),
        ];
    }
}
