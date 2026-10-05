<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Application\AppUrl;
use App\Domain\Activity\Image\ActivityImageId;
use App\Domain\Image\ImagePath;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ActivityImageResponse extends JsonResponse
{
    public static function created(ImagePath $path, AppUrl $appUrl): self
    {
        return new self([
            'id' => (string) ActivityImageId::fromImagePath($path),
            'url' => rtrim((string) $appUrl, '/').'/'.$path->toLocalImagePath(),
        ], Response::HTTP_CREATED);
    }
}
