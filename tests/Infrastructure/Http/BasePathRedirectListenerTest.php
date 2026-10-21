<?php

namespace App\Tests\Infrastructure\Http;

use App\Application\AppUrl;
use App\Infrastructure\Http\BasePathRedirectListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class BasePathRedirectListenerTest extends TestCase
{
    #[DataProvider('provideRedirects')]
    public function testItPrefixesRedirectsToThisApp(string $appUrl, string $location, string $expected): void
    {
        $response = new RedirectResponse($location);

        $this->dispatch($appUrl, $response);

        $this->assertSame($expected, $response->headers->get('Location'));
        $this->assertSame($expected, $response->getTargetUrl());
    }

    public static function provideRedirects(): iterable
    {
        yield 'root-relative path' => ['http://localhost:8080/dreeve', '/finish-setup', '/dreeve/finish-setup'];
        yield 'root' => ['http://localhost:8080/dreeve/', '/', '/dreeve/'];
        yield 'with query and fragment' => ['http://localhost:8080/dreeve', '/activities?a=b#c', '/dreeve/activities?a=b#c'];
        yield 'nested base path' => ['http://localhost:8080/apps/dreeve/', '/admin/login', '/apps/dreeve/admin/login'];
        yield 'absolute url on same origin' => ['http://localhost:8080/dreeve', 'http://localhost:8080/admin/login', 'http://localhost:8080/dreeve/admin/login'];
        yield 'absolute url on same origin with query' => ['http://localhost:8080/dreeve', 'http://localhost:8080/admin?x=1', 'http://localhost:8080/dreeve/admin?x=1'];
    }

    #[DataProvider('provideUntouchedRedirects')]
    public function testItLeavesOtherRedirectsAlone(string $appUrl, string $location): void
    {
        $response = new RedirectResponse($location);

        $this->dispatch($appUrl, $response);

        $this->assertSame($location, $response->headers->get('Location'));
    }

    public static function provideUntouchedRedirects(): iterable
    {
        yield 'no base path' => ['http://localhost:8080', '/finish-setup'];
        yield 'no base path, trailing slash' => ['http://localhost:8080/', '/finish-setup'];
        yield 'other origin' => ['http://localhost:8080/dreeve', 'https://picsum.photos/seed/a/800/1200'];
        yield 'other host sharing a prefix' => ['http://localhost:8080/dreeve', 'http://localhost:8080.evil.com/x'];
        yield 'protocol-relative url' => ['http://localhost:8080/dreeve', '//evil.com/x'];
    }

    public function testItIgnoresNonRedirects(): void
    {
        $response = new Response('ok');
        $response->headers->set('Location', '/created');

        $this->dispatch('http://localhost:8080/dreeve', $response);

        $this->assertSame('/created', $response->headers->get('Location'));
    }

    public function testItIgnoresSubRequests(): void
    {
        $response = new RedirectResponse('/finish-setup');

        $listener = new BasePathRedirectListener(AppUrl::fromString('http://localhost:8080/dreeve'));
        $listener->onKernelResponse(new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('http://localhost:8080/'),
            HttpKernelInterface::SUB_REQUEST,
            $response,
        ));

        $this->assertSame('/finish-setup', $response->headers->get('Location'));
    }

    private function dispatch(string $appUrl, Response $response): void
    {
        $listener = new BasePathRedirectListener(AppUrl::fromString($appUrl));
        $listener->onKernelResponse(new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('http://localhost:8080/'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        ));
    }
}
