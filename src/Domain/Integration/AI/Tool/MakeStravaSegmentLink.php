<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

final class MakeStravaSegmentLink extends Tool
{
    #[\Override]
    protected string $name = 'make_strava_segment_link';

    #[\Override]
    protected ?string $description = <<<DESC
        Generates a direct Strava URL for a specific segment using its unique segment ID.
        Use this tool when the user wants a link to view a segment on Strava. 
        It requires the segment ID and returns a full URL to the corresponding Strava segment page.
        Custom segments do not exist on Strava, so no link can be generated for them.
        DESC;

    public function __construct(
        private readonly SegmentRepository $segmentRepository,
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
                name: 'segmentId',
                type: PropertyType::STRING,
                description: 'The id of the segment.',
                required: true
            ),
        ];
    }

    public function __invoke(string $segmentId): string
    {
        $segment = $this->segmentRepository->find(SegmentId::fromUnprefixed($segmentId));

        return $segment->getStravaUrl() ?? sprintf('Segment %s is a custom segment and does not exist on Strava.', $segmentId);
    }
}
