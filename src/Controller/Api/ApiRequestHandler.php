<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Infrastructure\Serialization\Json;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ApiRequestHandler
{
    public function __construct(
        private FilesystemOperator $buildApiStorage,
    ) {
    }

    #[Route(path: '/api/{path}', name: 'api', requirements: ['path' => '[a-zA-Z0-9_\-/.]+'], methods: ['GET'], priority: 2)]
    public function handle(string $path): Response
    {
        try {
            if (!$this->buildApiStorage->fileExists($path)) {
                return new Response('', Response::HTTP_NOT_FOUND);
            }

            $fileContents = $this->buildApiStorage->read($path);

            return new JsonResponse(
                data: Json::uncompressAndDecode($fileContents),
                status: Response::HTTP_OK
            );
        } catch (UnableToReadFile) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
    }
}
