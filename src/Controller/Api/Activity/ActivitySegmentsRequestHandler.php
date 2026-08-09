<?php

declare(strict_types=1);

namespace App\Controller\Api\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ActivitySegmentsRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/activity/{activityId}/segments', name: 'api_activity_segments', methods: ['GET'], priority: 3)]
    public function handle(string $activityId): HtmlResponse
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        return new HtmlResponse($this->twig->load('html/activity/_segments.html.twig')->render([
            'segmentEfforts' => $this->segmentEffortRepository->findByActivityId($activity->getId()),
            'sportType' => $activity->getSportType(),
        ]));
    }
}
