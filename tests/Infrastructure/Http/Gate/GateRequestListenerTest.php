<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Infrastructure\Http\Gate\GateDecision;
use App\Infrastructure\Http\Gate\GateRequestListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class GateRequestListenerTest extends TestCase
{
    public function testItSetsTheResponseOfTheFirstInterceptingGate(): void
    {
        $redirect = new RedirectResponse('/gated');
        $listener = new GateRequestListener([
            new GateStub(GateDecision::defer()),
            new GateStub(GateDecision::respond($redirect)),
            new GateStub(GateDecision::respond(new RedirectResponse('/never-reached'))),
        ]);

        $event = new RequestEvent(
            kernel: $this->createStub(HttpKernelInterface::class),
            request: Request::create('/dashboard'),
            requestType: HttpKernelInterface::MAIN_REQUEST,
        );
        $listener->onKernelRequest($event);

        $this->assertSame($redirect, $event->getResponse());
    }

    public function testItInvokesGatesInOrderAndStopsAtTheFirstThatIntercepts(): void
    {
        /** @var \ArrayObject<int, string> $calls */
        $calls = new \ArrayObject();
        $listener = new GateRequestListener([
            new GateStub(calls: $calls, name: 'first', decision: GateDecision::defer()),
            new GateStub(calls: $calls, name: 'second', decision: GateDecision::respond(new RedirectResponse('/gated'))),
            new GateStub(calls: $calls, name: 'third', decision: GateDecision::respond(new RedirectResponse('/never-reached'))),
        ]);

        $event = new RequestEvent(
            kernel: $this->createStub(HttpKernelInterface::class),
            request: Request::create('/dashboard'),
            requestType: HttpKernelInterface::MAIN_REQUEST,
        );
        $listener->onKernelRequest($event);

        // The third gate is never reached once the second one intercepts.
        $this->assertSame(['first', 'second'], $calls->getArrayCopy());
        $this->assertSame('/gated', $event->getResponse()?->getTargetUrl());
    }

    public function testItStopsAtAGateThatAllowsTheRequest(): void
    {
        /** @var \ArrayObject<int, string> $calls */
        $calls = new \ArrayObject();
        $listener = new GateRequestListener([
            new GateStub(calls: $calls, name: 'first', decision: GateDecision::allow()),
            new GateStub(calls: $calls, name: 'second', decision: GateDecision::respond(new RedirectResponse('/never-reached'))),
        ]);

        $event = new RequestEvent(
            kernel: $this->createStub(HttpKernelInterface::class),
            request: Request::create('/admin/settings/athlete'),
            requestType: HttpKernelInterface::MAIN_REQUEST,
        );
        $listener->onKernelRequest($event);

        // The first gate is guarding and keeps this path open. The gates behind it must not
        // redirect the user away from it.
        $this->assertSame(['first'], $calls->getArrayCopy());
        $this->assertNull($event->getResponse());
    }

    public function testItLetsTheRequestThroughWhenNoGateIntercepts(): void
    {
        $listener = new GateRequestListener([new GateStub(GateDecision::defer()), new GateStub(GateDecision::defer())]);

        $event = new RequestEvent(
            kernel: $this->createStub(HttpKernelInterface::class),
            request: Request::create('/dashboard'),
            requestType: HttpKernelInterface::MAIN_REQUEST,
        );
        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    #[DataProvider('provideAlwaysOpenPaths')]
    public function testItNeverGatesAlwaysOpenPaths(string $path): void
    {
        $listener = new GateRequestListener([new GateStub(GateDecision::respond(new RedirectResponse('/gated')))]);

        $event = new RequestEvent(
            kernel: $this->createStub(HttpKernelInterface::class),
            request: Request::create($path),
            requestType: HttpKernelInterface::MAIN_REQUEST,
        );
        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAlwaysOpenPaths(): iterable
    {
        yield 'strava webhook' => ['/strava/webhook'];
        yield 'profiler' => ['/_profiler/abc123'];
        yield 'web debug toolbar' => ['/_wdt/abc123'];
        yield 'css asset' => ['/css/app.css'];
        yield 'stored image' => ['/files/gear/bike.png'];
        yield 'mcp server' => ['/mcp'];
    }

    public function testItDoesNothingForSubRequests(): void
    {
        $listener = new GateRequestListener([new GateStub(GateDecision::respond(new RedirectResponse('/gated')))]);

        $event = new RequestEvent(
            kernel: $this->createStub(HttpKernelInterface::class),
            request: Request::create('/dashboard'),
            requestType: HttpKernelInterface::SUB_REQUEST,
        );
        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }
}
