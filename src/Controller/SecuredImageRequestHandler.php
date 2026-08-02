<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Security\TrustedVisitor;
use App\Infrastructure\ValueObject\String\Path;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class SecuredImageRequestHandler
{
    public function __construct(
        private FilesystemOperator $fileStorage,
        private TrustedVisitor $trustedVisitor,
    ) {
    }

    #[Route(path: '/secured-image/{path}', name: 'secured_image', requirements: ['path' => '.+'], methods: ['GET'], priority: 2)]
    public function handle(string $path): Response
    {
        if (!$this->fileStorage->fileExists($path)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        if (!$this->trustedVisitor->isTrusted()) {
            // Not a trusted visitor: serve an anonymized, stable random photo instead of the real one.
            $seed = Path::fromString($path)->getFilenameWithoutExtension();
            [$width, $height] = 0 === crc32($seed) % 2 ? [800, 1200] : [1200, 800];

            return new RedirectResponse(sprintf('https://picsum.photos/seed/%s/%d/%d', urlencode($seed), $width, $height));
        }

        $stream = $this->fileStorage->readStream($path);

        return new StreamedResponse(
            function () use ($stream): void {
                fpassthru($stream);
            },
            Response::HTTP_OK,
            ['Content-Type' => $this->fileStorage->mimeType($path)]
        );
    }
}
