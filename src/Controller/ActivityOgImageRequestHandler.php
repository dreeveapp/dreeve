<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\OpenGraph\ActivityOpenGraphImage;
use App\Infrastructure\Exception\EntityNotFound;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityOgImageRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityOpenGraphImage $activityOpenGraphImage,
    ) {
    }

    #[Route(path: '/activities/{activityId}/og-image.png', name: 'activity_og_image', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'], priority: 3)]
    public function handle(string $activityId): Response
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response($this->activityOpenGraphImage->render($activity), Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
