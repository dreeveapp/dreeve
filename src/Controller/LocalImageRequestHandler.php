<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Image\ImageDirectory;
use App\Infrastructure\Security\TrustedVisitor;
use App\Infrastructure\ValueObject\String\Path;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class LocalImageRequestHandler
{
    /** @var list<string> */
    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

    public function __construct(
        private FilesystemOperator $fileStorage,
        private TrustedVisitor $trustedVisitor,
    ) {
    }

    #[Route(path: '/files/{path}', name: 'local_image', requirements: ['path' => '.+'], methods: ['GET'], priority: 3)]
    public function handle(string $path): Response
    {
        if (str_contains($path, '..')
            || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true)
            || !$this->fileStorage->fileExists($path)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $isActivityPhoto = str_starts_with($path, ImageDirectory::ACTIVITIES->value.'/');
        $cachingHeaders = [
            // Activity photos are the only ones that get anonymized, so the same URL can answer with
            // the real photo or with a placeholder depending on state the browser cannot observe.
            // Storing them would keep serving the real photo after demo mode is switched on.
            // Every other file is written once under a unique name and never changes in place.
            'Cache-Control' => $isActivityPhoto ? 'private, no-store' : 'private, max-age=31536000, immutable',
            'Vary' => 'Cookie',
        ];

        if ($isActivityPhoto && !$this->trustedVisitor->isTrusted()) {
            // Not a trusted visitor: serve an anonymized, stable random photo instead of the real one.
            $seed = Path::fromString($path)->getFilenameWithoutExtension();
            [$width, $height] = 0 === crc32($seed) % 2 ? [800, 1200] : [1200, 800];

            return new RedirectResponse(
                sprintf('https://picsum.photos/seed/%s/%d/%d', urlencode($seed), $width, $height),
                Response::HTTP_FOUND,
                $cachingHeaders
            );
        }

        $stream = $this->fileStorage->readStream($path);

        return new StreamedResponse(
            function () use ($stream): void {
                fpassthru($stream);
            },
            Response::HTTP_OK,
            [...$cachingHeaders, 'Content-Type' => $this->fileStorage->mimeType($path)]
        );
    }
}
