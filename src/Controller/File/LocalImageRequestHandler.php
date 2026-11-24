<?php

declare(strict_types=1);

namespace App\Controller\File;

use App\Domain\Media\MediaItemDirectory;
use App\Infrastructure\Http\Response\CachePolicy;
use App\Infrastructure\Security\Visitor;
use App\Infrastructure\ValueObject\String\Path;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Time\Duration;

#[AsController]
final readonly class LocalImageRequestHandler
{
    private const int ANONYMIZED_IMAGE_COUNT = 20;

    public function __construct(
        #[Target('file.storage')]
        private FilesystemOperator $fileStorage,
        private Visitor $visitor,
    ) {
    }

    #[Route(path: '/files/{path}', name: 'local_image', requirements: ['path' => '.+'], methods: ['GET'])]
    public function handle(string $path): Response
    {
        if (!$this->findServableImage($path) instanceof Path) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $stream = $this->fileStorage->readStream($path);

        return CachePolicy::private()->maxAge(Duration::fromHours(365 * 24))->immutable()->applyTo(new StreamedResponse(
            function () use ($stream): void {
                fpassthru($stream);
            },
            Response::HTTP_OK,
            ['Content-Type' => $this->fileStorage->mimeType($path)]
        ));
    }

    #[Route(path: '/files/activities/{filename}', name: 'activity_photo', requirements: ['filename' => '.+'], methods: ['GET'], priority: 1)]
    public function handleActivityPhoto(string $filename, Request $request): Response
    {
        $path = MediaItemDirectory::ACTIVITIES->value.'/'.$filename;

        $image = $this->findServableImage($path);
        if (!$image instanceof Path) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        if (!$this->visitor->isTrusted()) {
            return CachePolicy::private()->noStore()->applyTo(new RedirectResponse(
                url: sprintf('/assets/images/anonymized/%02d.webp', crc32($image->getFilenameWithoutExtension()) % self::ANONYMIZED_IMAGE_COUNT + 1),
                status: Response::HTTP_FOUND,
                headers: ['Vary' => 'Cookie'],
            ));
        }

        $headers = ['Vary' => 'Cookie', 'Content-Type' => $this->fileStorage->mimeType($path)];
        $etag = hash('xxh128', $path.'|'.$this->fileStorage->lastModified($path).'|'.$this->fileStorage->fileSize($path));
        $cachePolicy = CachePolicy::private()->revalidateWithEtag($etag);

        $notModifiedResponse = $cachePolicy->applyTo(new Response(headers: $headers));
        if ($notModifiedResponse->isNotModified($request)) {
            return $notModifiedResponse;
        }

        $stream = $this->fileStorage->readStream($path);

        return $cachePolicy->applyTo(new StreamedResponse(
            callbackOrChunks: function () use ($stream): void {
                fpassthru($stream);
            },
            status: Response::HTTP_OK,
            headers: $headers,
        ));
    }

    private function findServableImage(string $path): ?Path
    {
        $image = Path::fromString($path);

        if ($image->containsTraversal() || !$image->isSupportedImage() || !$this->fileStorage->fileExists($path)) {
            return null;
        }

        return $image;
    }
}
