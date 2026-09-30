<?php

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\PrivateNoStoreHtmlResponse;
use PHPUnit\Framework\TestCase;

class PrivateNoStoreHtmlResponseTest extends TestCase
{
    public function testItIsNeverStoredByTheBrowser(): void
    {
        $response = new PrivateNoStoreHtmlResponse('<p>html</p>', ['X-Dreeve-Cache' => 'HIT', 'Cache-Control' => 'public, max-age=3600']);

        $this->assertEquals('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertEquals('HIT', $response->headers->get('X-Dreeve-Cache'));
        $this->assertEquals('no-store, private', $response->headers->get('Cache-Control'));
    }
}
