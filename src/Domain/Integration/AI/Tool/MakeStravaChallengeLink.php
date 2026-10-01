<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class MakeStravaChallengeLink extends Tool
{
    #[\Override]
    protected string $name = 'make_strava_challenge_link';

    #[\Override]
    protected ?string $description = <<<DESC
        Generates a direct Strava URL for a specific challenge using its unique slug.
        Use this tool when the user wants a link to view a challenge on Strava. 
        It requires the challenge slug and returns a full URL to the corresponding Strava challenge page.
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
                name: 'slug',
                type: PropertyType::STRING,
                description: 'The slug of the challenge.',
                required: true
            ),
        ];
    }

    public function __invoke(string $slug): string
    {
        return sprintf('https://www.strava.com/challenges/%s', $slug);
    }
}
