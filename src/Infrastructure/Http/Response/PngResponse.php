<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Response;

use Symfony\Component\HttpFoundation\Response;

class PngResponse extends Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $png, array $headers = [])
    {
        parent::__construct($png, Response::HTTP_OK, ['Content-Type' => 'image/png', ...$headers]);
    }
}
