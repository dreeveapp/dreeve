<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

final class GetAllActivitiesSummary extends Tool
{
    private const int DEFAULT_LIMIT = 25;
    private const int MAX_LIMIT = 250;

    #[\Override]
    protected string $name = 'get_activities_summary';

    #[\Override]
    protected ?string $description = <<<DESC
        Retrieves a list of the user’s activities, sorted from newest to oldest, along with summary data for each activity.
        By default only the 25 most recent activities are returned.
        Use this tool whenever you need to identify which activities to include in a query or summary.
        For example, when the user asks for insights like “Summarize my last week’s activities” or "Compare my last three rides."
        When the user asks about a specific period, like "last week", "March 2025" or "this year", pass the "from" and "to" dates instead of fetching more activities.
        Only raise the "limit" when you need more recent activities than the default returns.
        This tool helps you determine which specific activities match the user’s time range or criteria, so you can use their IDs in subsequent tool calls.
        DESC;

    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly ActivityIdRepository $activityIdRepository,
    ) {
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
                name: 'limit',
                type: PropertyType::INTEGER,
                description: 'Maximum number of activities to return, newest first. Defaults to 25, at most 250.',
                required: false
            ),
            new ToolProperty(
                name: 'from',
                type: PropertyType::STRING,
                description: 'Only include activities on or after this date (YYYY-MM-DD).',
                required: false
            ),
            new ToolProperty(
                name: 'to',
                type: PropertyType::STRING,
                description: 'Only include activities on or before this date (YYYY-MM-DD).',
                required: false
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(?int $limit, ?string $from, ?string $to): array
    {
        $limit = max(1, min($limit ?? self::DEFAULT_LIMIT, self::MAX_LIMIT));
        $activities = null === $from && null === $to
            ? $this->activityRepository->findMostRecent($limit)
            : $this->activityRepository->findByDateRange(
                from: SerializableDateTime::fromString($from ?? '1970-01-01'),
                till: SerializableDateTime::fromString(($to ?? '9999-12-30').' +1 day'),
            )->slice(0, $limit);

        return [
            'activitiesSummary' => $activities->map(fn (Activity $activity): array => [
                'id' => $activity->getId()->toUnprefixedString(),
                'on' => $activity->getStartDate()->format('Y-m-d'),
            ]),
            'totalActivityCount' => $this->activityIdRepository->count(),
        ];
    }
}
