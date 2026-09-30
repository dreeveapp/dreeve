<?php

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\SvgResponse;
use PHPUnit\Framework\TestCase;

class SvgResponseTest extends TestCase
{
    public function testItAddsTheGivenHeaders(): void
    {
        $response = new SvgResponse('<svg></svg>', ['X-Dreeve-Cache' => 'MISS']);

        $this->assertEquals('image/svg+xml; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertEquals('MISS', $response->headers->get('X-Dreeve-Cache'));
    }
}
