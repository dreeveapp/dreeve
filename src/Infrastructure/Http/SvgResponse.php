<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;

class SvgResponse extends Response
{
    public function __construct(string $svg)
    {
        parent::__construct($svg, Response::HTTP_OK, ['Content-Type' => 'image/svg+xml; charset=UTF-8']);
    }
}
