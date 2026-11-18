<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Cache\Render\Render;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ConditionalJsonResponse extends JsonResponse
{
    public function __construct(
        Render $render,
        Request $request,
        string $fallbackContent = '[]',
    ) {
        $content = $render->getContent() ?? $fallbackContent;

        parent::__construct(
            data: $content,
            headers: [
                ...$render->getCacheHeaders(),
                'Cache-Control' => 'private, no-cache',
            ],
            json: true,
        );

        $etag = $render->getEtag() ?? hash('sha256', $content);
        $this->setEtag($etag);
        $this->isNotModified($request);
    }
}
