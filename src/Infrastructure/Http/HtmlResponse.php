<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;

class HtmlResponse extends Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $html, array $headers = [])
    {
        parent::__construct($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8', ...$headers]);
    }
}
