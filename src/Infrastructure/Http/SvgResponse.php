<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;

class SvgResponse extends Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $svg, array $headers = [])
    {
        parent::__construct($svg, Response::HTTP_OK, ['Content-Type' => 'image/svg+xml; charset=UTF-8', ...$headers]);
    }
}
