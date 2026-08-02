<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Http\Page\PageRegistry;
use App\Infrastructure\Http\Page\PageRenderer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\CompressedString;
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
        private PageRegistry $pageRegistry,
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/api/{path}', name: 'api', requirements: ['path' => '[a-zA-Z0-9_\-/.]+'], methods: ['GET'], priority: 2)]
    public function handle(string $path): Response
    {
        if ($page = $this->pageRegistry->find($path)) {
            return $this->pageRenderer->render($page);
        }

        try {
            if (!$this->buildApiStorage->fileExists($path)) {
                return new Response('', Response::HTTP_NOT_FOUND);
            }

            $fileContents = $this->buildApiStorage->read($path);

            if (str_ends_with($path, '.gpx')) {
                $response = new Response(CompressedString::fromCompressed($fileContents)->uncompress());
                $response->headers->set('Content-Type', 'application/gpx+xml; charset=UTF-8');

                return $response;
            }

            return new JsonResponse(
                data: Json::uncompressAndDecode($fileContents),
                status: Response::HTTP_OK
            );
        } catch (UnableToReadFile) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
    }
}
