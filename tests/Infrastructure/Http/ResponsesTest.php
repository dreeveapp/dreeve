<?php

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Http\PrivateNoStoreHtmlResponse;
use App\Infrastructure\Http\SvgResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ResponsesTest extends TestCase
{
    /**
     * @param array<string, string> $expectedHeaders
     */
    #[DataProvider('provideResponses')]
    public function testItAddsTheGivenHeaders(Response $response, array $expectedHeaders): void
    {
        foreach ($expectedHeaders as $name => $value) {
            $this->assertEquals($value, $response->headers->get($name), $name);
        }
    }

    public static function provideResponses(): iterable
    {
        yield 'html' => [
            new HtmlResponse('<p>html</p>', ['X-Dreeve-Cache' => 'HIT', 'Cache-Control' => 'private, no-store']),
            ['Content-Type' => 'text/html; charset=UTF-8', 'X-Dreeve-Cache' => 'HIT', 'Cache-Control' => 'no-store, private'],
        ];
        yield 'private no store html is never stored by the browser' => [
            new PrivateNoStoreHtmlResponse('<p>html</p>', ['X-Dreeve-Cache' => 'HIT', 'Cache-Control' => 'public, max-age=3600']),
            ['Content-Type' => 'text/html; charset=UTF-8', 'X-Dreeve-Cache' => 'HIT', 'Cache-Control' => 'no-store, private'],
        ];
        yield 'svg' => [
            new SvgResponse('<svg></svg>', ['X-Dreeve-Cache' => 'MISS']),
            ['Content-Type' => 'image/svg+xml; charset=UTF-8', 'X-Dreeve-Cache' => 'MISS'],
        ];
    }
}
