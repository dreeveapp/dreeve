<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\EnrichedActivityRepository;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

final class GetActivity extends Tool
{
    public function __construct(
        private readonly EnrichedActivityRepository $enrichedActivityRepository,
    ) {
        parent::__construct(
            'get_activity_by_id',
            <<<DESC
            Retrieves detailed information for a single activity, identified by its unique ID.
            Use this tool when the user refers to a specific activity or asks for details about a particular workout.
            It requires the activity ID as input and provides the full activity data needed for summaries, comparisons, or insights. 
            Example requests include “Show details for activity 12345” or “Compare my Sunday ride with activity 67890.”
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

        return $this->enrichedActivityRepository->find($activityId)->exportForAITooling();
    }
}
