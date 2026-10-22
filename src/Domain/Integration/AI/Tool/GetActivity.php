<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Application\AppUrl;
use App\Domain\Activity\EnrichedActivityRepository;
use App\Infrastructure\Exception\EntityNotFound;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class GetActivity implements AITool
{
    public function __construct(
        private EnrichedActivityRepository $enrichedActivityRepository,
        private AppUrl $appUrl,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getName(): string
    {
        return 'get_activity_by_id';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves detailed information for a single activity, identified by its unique ID.
            Use this tool when the user refers to a specific activity or asks for details about a particular workout.
            It requires the activity ID as input and provides the full activity data needed for summaries, comparisons, or insights.
            Example requests include “Show details for activity 12345” or “Compare my Sunday ride with activity 67890.”
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
        $activityId = $arguments->getActivityId();

        try {
            $activity = $this->enrichedActivityRepository->find($activityId);
        } catch (EntityNotFound) {
            throw new ToolFailed(sprintf('Activity "%s" does not exist.', $activityId));
        }

        return [
            ...$activity->exportForAITooling(),
            'url' => (string) $this->appUrl->withPath($this->urlGenerator->generate('activity', ['activityId' => (string) $activityId])),
        ];
    }
}
