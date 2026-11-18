<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

class PrivateNoCacheJsonResponse extends JsonResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $json, array $headers = [])
    {
        parent::__construct($json, headers: [...$headers, 'Cache-Control' => 'private, no-cache'], json: true);
        $this->setEtag(hash('xxh128', $json));
    }
}
