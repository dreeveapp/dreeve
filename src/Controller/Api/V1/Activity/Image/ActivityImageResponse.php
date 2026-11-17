<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity\Image;

use App\Application\AppUrl;
use App\Domain\Activity\Media\ActivityImageId;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemPath;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ActivityImageResponse extends JsonResponse
{
    public static function created(MediaItemId $mediaItemId, MediaItemPath $path, AppUrl $appUrl): self
    {
        return new self([
            'id' => (string) ActivityImageId::fromUnprefixed($mediaItemId->toUnprefixedString()),
            'url' => rtrim((string) $appUrl, '/').$path->toUrlPath(),
        ], Response::HTTP_CREATED);
    }
}
