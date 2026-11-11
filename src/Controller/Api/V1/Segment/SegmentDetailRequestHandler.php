<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Segment;

use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\Exception\EntityNotFound;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class SegmentDetailRequestHandler
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private SegmentEffortRepository $segmentEffortRepository,
    ) {
    }

    #[Route(path: '/api/v1/segments/{segmentId}', name: 'api_v1_segment', methods: ['GET'], priority: 3)]
    public function handle(string $segmentId): SegmentResponse
    {
        try {
            $segment = $this->segmentRepository->find(SegmentId::fromString($segmentId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Segment "%s" not found', $segmentId));
        }

        return SegmentResponse::detail(
            segment: $segment,
            numberOfTimesRidden: count($this->segmentEffortRepository->findBySegmentId($segment->getId())),
            bestEffort: $this->segmentEffortRepository->findTopXBySegmentId(
                segmentId: $segment->getId(),
                limit: 1,
            )->getFirst(),
        );
    }
}
