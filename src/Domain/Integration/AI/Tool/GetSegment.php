<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Application\AppUrl;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\Exception\EntityNotFound;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class GetSegment implements AITool
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private AppUrl $appUrl,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getName(): string
    {
        return 'get_segment_by_id';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves detailed information about a specific segment using its unique ID, including name, distance, elevation, sport type, and climb category.
            Use this tool when the user asks about a specific segment, for example “Tell me more about segment 1234” or “What’s the length of that climb?”.
            It provides the full segment data needed for summaries, comparisons, or analysis.
            DESC;
    }

    public function getParameters(): array
    {
        return [
            ToolParameter::string('segmentId', 'The id of the segment.', required: true),
        ];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        $segmentId = $arguments->getSegmentId();

        try {
            $segment = $this->segmentRepository->find($segmentId);
        } catch (EntityNotFound) {
            throw new ToolFailed(sprintf('Segment "%s" does not exist.', $segmentId));
        }

        return [
            ...$segment->exportForAITooling(),
            'url' => (string) $this->appUrl->withPath($this->urlGenerator->generate('segment', ['segmentId' => (string) $segmentId])),
        ];
    }
}
