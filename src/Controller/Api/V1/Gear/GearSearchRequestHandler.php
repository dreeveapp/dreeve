<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Gear;

use App\Domain\Gear\Gear;
use App\Domain\Gear\GearRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GearSearchRequestHandler
{
    public function __construct(
        private GearRepository $gearRepository,
    ) {
    }

    #[Route(path: '/api/v1/gear', name: 'api_v1_gear', methods: ['GET'], priority: 3)]
    public function handle(): JsonResponse
    {
        return new JsonResponse([
            'gear' => array_map(
                static fn (Gear $gear): array => [
                    'id' => (string) $gear->getId(),
                    'name' => $gear->getOriginalName(),
                    'type' => $gear->getType()->value,
                    'isRetired' => $gear->isRetired(),
                    'distanceInMeter' => $gear->getDistance()->toMeter()->toInt(),
                    'numberOfActivities' => $gear->getNumberOfActivities(),
                ],
                $this->gearRepository->findAll()->toArray()
            ),
        ]);
    }
}
