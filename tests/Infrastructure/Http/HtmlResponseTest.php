<?php

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\HtmlResponse;
use PHPUnit\Framework\TestCase;

class HtmlResponseTest extends TestCase
{
    public function testItAddsTheGivenHeaders(): void
    {
        $response = new HtmlResponse('<p>html</p>', ['X-Dreeve-Cache' => 'HIT', 'Cache-Control' => 'private, no-store']);

        $this->assertEquals('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertEquals('HIT', $response->headers->get('X-Dreeve-Cache'));
        $this->assertEquals('no-store, private', $response->headers->get('Cache-Control'));
    }
}
