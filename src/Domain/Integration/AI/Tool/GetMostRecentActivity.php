<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Application\AppUrl;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\EnrichedActivityRepository;
use App\Infrastructure\Exception\EntityNotFound;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class GetMostRecentActivity implements AITool
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private EnrichedActivityRepository $enrichedActivityRepository,
        private AppUrl $appUrl,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getName(): string
    {
        return 'get_most_recent_activity';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves the user’s most recent activity, including its ID and summary details.
            Use this tool whenever the user refers to “my last activity” or asks for information about their most recent workout or ride. For example, “Show stats from my last activity”.
            This tool helps you quickly identify the latest activity to use in further analysis or comparisons.
            DESC;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        if (!$mostRecentActivity = $this->activityRepository->findMostRecent(1)->getFirst()) {
            throw new EntityNotFound('No activities found');
        }

        return [
            ...$this->enrichedActivityRepository->find($mostRecentActivity->getId())->exportForAITooling(),
            'url' => (string) $this->appUrl->withPath($this->urlGenerator->generate('activity', ['activityId' => (string) $mostRecentActivity->getId()])),
        ];
    }
}
