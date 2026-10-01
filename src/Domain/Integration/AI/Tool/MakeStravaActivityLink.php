<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

final class MakeStravaActivityLink extends Tool
{
    #[\Override]
    protected string $name = 'make_strava_activity_link';

    #[\Override]
    protected ?string $description = <<<DESC
        Generates a direct Strava URL for a specific activity using its unique activity ID.
        Use this tool when the user wants a link to view an activity on Strava or when detailed activity data is requested
        It requires the activity ID and returns a full URL to the corresponding Strava activity page. 
        Example requests include “Give me a link to my last ride on Strava” or “Show me activity 12345 on Strava.”
        DESC;

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
        return 100;
    }

    public function __invoke(string $activityId): string
    {
        return sprintf('https://www.strava.com/activities/%s', $activityId);
    }
}
