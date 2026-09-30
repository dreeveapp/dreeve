<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

class PrivateNoStoreHtmlResponse extends HtmlResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $html, array $headers = [])
    {
        parent::__construct($html, [...$headers, 'Cache-Control' => 'private, no-store']);
    }
}
