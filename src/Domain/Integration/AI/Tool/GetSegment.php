<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

final class GetSegment extends Tool
{
    #[\Override]
    protected string $name = 'get_segment_by_id';

    #[\Override]
    protected ?string $description = <<<DESC
        Retrieves detailed information about a specific segment using its unique ID, including name, distance, elevation, sport type, and climb category.
        Use this tool when the user asks about a specific segment, for example “Tell me more about segment 1234” or “What’s the length of that climb?”. 
        It provides the full segment data needed for summaries, comparisons, or analysis.
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

    /**
     * @return array<string, mixed>
     */
    public function __invoke(string $segmentId): array
    {
        $segmentId = SegmentId::fromUnprefixed($segmentId);

        return $this->segmentRepository->find($segmentId)->exportForAITooling();
    }
}
