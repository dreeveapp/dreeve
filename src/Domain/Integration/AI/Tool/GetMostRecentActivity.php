<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\EnrichedActivityRepository;
use App\Infrastructure\Exception\EntityNotFound;
use NeuronAI\Tools\Tool;

final class GetMostRecentActivity extends Tool
{
    public function __construct(
        private readonly EnrichedActivityRepository $enrichedActivityRepository,
    ) {
        parent::__construct(
            'get_most_recent_activity',
            <<<DESC
            Retrieves the user’s most recent activity, including its ID and summary details.
            Use this tool whenever the user refers to “my last activity” or asks for information about their most recent workout or ride. For example, “Show stats from my last activity”.
            This tool helps you quickly identify the latest activity to use in further analysis or comparisons.
            DESC
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        // findAll() is ordered on startDateTime DESC, so the first one is the most recent.
        $allActivities = $this->enrichedActivityRepository->findAll();
        if (!$mostRecentActivity = reset($allActivities)) {
            throw new EntityNotFound('No activities found');
        }

        return $mostRecentActivity->exportForAITooling();
    }
}
