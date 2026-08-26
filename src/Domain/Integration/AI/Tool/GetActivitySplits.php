<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Split\ActivitySplit;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Settings\SettingsRepository;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

final class GetActivitySplits extends Tool
{
    private const int MAX_RESULTS = 60;

    public function __construct(
        private readonly ActivitySplitRepository $activitySplitRepository,
        private readonly SettingsRepository $settingsRepository,
    ) {
        parent::__construct(
            'get_activity_splits',
            <<<DESC
            Retrieves detailed split information for a specific activity using its unique activity ID.
            Use this tool when the user asks about split data within an activity or requests all details for a specific activity. 
            It requires the activity ID as input and provides the split-by-split breakdown needed for summaries, analysis, or comparisons. 
            Example requests include “Show all splits for activity 12345” or “Give me detailed split stats for my last run.”
            Returns at most 60 splits, from the start of the activity. Compare "totalSplitCount" with the number of returned splits: if it is higher, tell the user you are only seeing part of the activity.
            DESC
        );
    }

    #[\Override]
    public function getMaxRuns(): int
    {
        return 25;
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

    /**
     * @return array<string, mixed>
     */
    public function __invoke(string $activityId): array
    {
        $activityId = ActivityId::fromUnprefixed($activityId);
        $splits = $this->activitySplitRepository->findBy(
            activityId: $activityId,
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
