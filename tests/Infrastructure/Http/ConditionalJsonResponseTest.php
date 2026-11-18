<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Cache\Render\Render;
use App\Infrastructure\Http\ConditionalJsonResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ConditionalJsonResponseTest extends TestCase
{
    public function testItReturns200WithEtagAndCacheControlOnFirstRequest(): void
    {
        $content = '{"message":"hello"}';
        $render = Render::freshlyRendered($content, 'test.key', ['test.tag']);
        $request = Request::create('/api/test');

        $response = new ConditionalJsonResponse($render, $request);

        $expectedEtag = '"'.hash('sha256', $content).'"';
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($expectedEtag, $response->headers->get('ETag'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('test.key', $response->headers->get('X-Dreeve-Cache-Key'));
        $this->assertSame('test.tag', $response->headers->get('X-Dreeve-Cache-Tags'));
        $this->assertSame($content, $response->getContent());
    }

    public function testItReturns304WithEmptyBodyWhenIfNoneMatchMatches(): void
    {
        $content = '{"message":"hello"}';
        $render = Render::freshlyRendered($content, 'test.key');
        $expectedEtag = '"'.hash('sha256', $content).'"';

        $request = Request::create('/api/test');
        $request->headers->set('If-None-Match', $expectedEtag);

        $response = new ConditionalJsonResponse($render, $request);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', (string) $response->getContent());
        $this->assertSame($expectedEtag, $response->headers->get('ETag'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function testItReturns200WhenIfNoneMatchDoesNotMatch(): void
    {
        $content = '{"message":"hello"}';
        $render = Render::freshlyRendered($content, 'test.key');

        $request = Request::create('/api/test');
        $request->headers->set('If-None-Match', '"different-etag"');

        $response = new ConditionalJsonResponse($render, $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($content, $response->getContent());
        $this->assertSame('"'.hash('sha256', $content).'"', $response->headers->get('ETag'));
    }

    public function testItSupportsWeakIfNoneMatch(): void
    {
        $content = '{"message":"hello"}';
        $render = Render::freshlyRendered($content, 'test.key');

        $request = Request::create('/api/test');
        $request->headers->set('If-None-Match', 'W/"'.hash('sha256', $content).'"');

        $response = new ConditionalJsonResponse($render, $request);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', (string) $response->getContent());
    }

    public function testItUsesFallbackContentWhenRenderContentIsNull(): void
    {
        $render = Render::freshlyRendered(null, 'test.key');
        $request = Request::create('/api/test');

        $response = new ConditionalJsonResponse($render, $request, '[]');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('[]', $response->getContent());
        $this->assertSame('"'.hash('sha256', '[]').'"', $response->headers->get('ETag'));
    }
}
