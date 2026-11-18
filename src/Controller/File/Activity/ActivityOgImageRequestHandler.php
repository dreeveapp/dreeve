<?php

declare(strict_types=1);

namespace App\Controller\File\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityOpenGraphImage;
use App\Domain\Activity\ActivityRepository;
use App\Infrastructure\Http\Response\CachePolicy;
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

    #[Route(path: '/activities/{activityId}/og-image.png', name: 'activity_og_image', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'])]
    public function handle(string $activityId): Response
    {
        $activity = $this->activityRepository->find(ActivityId::fromString($activityId));

        return CachePolicy::public()->maxAge(3600)->applyTo(new Response($this->activityOpenGraphImage->render($activity), Response::HTTP_OK, [
            'Content-Type' => 'image/png',
        ]));
    }
}
