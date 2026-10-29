<?php

declare(strict_types=1);

namespace App\Controller\File;

use App\Application\PageMetadata\DefaultOpenGraphImage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class OgImageRequestHandler
{
    public function __construct(
        private DefaultOpenGraphImage $defaultOpenGraphImage,
    ) {
    }

    #[Route(path: '/og-image.png', name: 'og_image', methods: ['GET'])]
    public function handle(): Response
    {
        return new Response($this->defaultOpenGraphImage->render(), Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
