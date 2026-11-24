<?php

declare(strict_types=1);

namespace App\Controller\File\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityOpenGraphImage;
use App\Domain\Activity\ActivityRepository;
use App\Infrastructure\Http\Response\CachePolicy;
use App\Infrastructure\Http\Response\PngResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Time\Duration;

#[AsController]
final readonly class ActivityOgImageRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityOpenGraphImage $activityOpenGraphImage,
    ) {
    }

    #[Route(path: '/activities/{activityId}/og-image.png', name: 'activity_og_image', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'])]
    public function handle(string $activityId): PngResponse
    {
        $activity = $this->activityRepository->find(ActivityId::fromString($activityId));

        return CachePolicy::public()->maxAge(Duration::fromHours(1))->applyTo(
            new PngResponse($this->activityOpenGraphImage->render($activity)),
        );
    }
}
