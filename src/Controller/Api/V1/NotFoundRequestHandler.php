<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class NotFoundRequestHandler
{
    #[Route(path: '/api/v1/{wildcard?}', name: 'api_v1_not_found', requirements: ['wildcard' => '.*'], priority: 2)]
    public function handle(): Response
    {
        throw new NotFoundHttpException('This API endpoint does not exist.');
    }
}
