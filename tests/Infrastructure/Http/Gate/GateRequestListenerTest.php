<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Infrastructure\Http\Gate\Gate;
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
            $this->gate(GateDecision::defer()),
            $this->gate(GateDecision::respond($redirect)),
            $this->gate(GateDecision::respond(new RedirectResponse('/never-reached'))),
        ]);

        $event = $this->mainRequest(Request::create('/dashboard'));
        $listener->onKernelRequest($event);

        $this->assertSame($redirect, $event->getResponse());
    }

    public function testItInvokesGatesInOrderAndStopsAtTheFirstThatIntercepts(): void
    {
        /** @var \ArrayObject<int, string> $calls */
        $calls = new \ArrayObject();
        $listener = new GateRequestListener([
            $this->recordingGate($calls, 'first', GateDecision::defer()),
            $this->recordingGate($calls, 'second', GateDecision::respond(new RedirectResponse('/gated'))),
            $this->recordingGate($calls, 'third', GateDecision::respond(new RedirectResponse('/never-reached'))),
        ]);

        $event = $this->mainRequest(Request::create('/dashboard'));
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
            $this->recordingGate($calls, 'first', GateDecision::allow()),
            $this->recordingGate($calls, 'second', GateDecision::respond(new RedirectResponse('/never-reached'))),
        ]);

        $event = $this->mainRequest(Request::create('/admin/settings/athlete'));
        $listener->onKernelRequest($event);

        // The first gate is guarding and keeps this path open. The gates behind it must not
        // redirect the user away from it.
        $this->assertSame(['first'], $calls->getArrayCopy());
        $this->assertNull($event->getResponse());
    }

    public function testItLetsTheRequestThroughWhenNoGateIntercepts(): void
    {
        $listener = new GateRequestListener([$this->gate(GateDecision::defer()), $this->gate(GateDecision::defer())]);

        $event = $this->mainRequest(Request::create('/dashboard'));
        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    #[DataProvider('provideAlwaysOpenPaths')]
    public function testItNeverGatesAlwaysOpenPaths(string $path): void
    {
        $listener = new GateRequestListener([$this->gate(GateDecision::respond(new RedirectResponse('/gated')))]);

        $event = $this->mainRequest(Request::create($path));
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
    }

    public function testItDoesNothingForSubRequests(): void
    {
        $listener = new GateRequestListener([$this->gate(GateDecision::respond(new RedirectResponse('/gated')))]);

        $event = new RequestEvent(
            kernel: $this->createStub(HttpKernelInterface::class),
            request: Request::create('/dashboard'),
            requestType: HttpKernelInterface::SUB_REQUEST,
        );
        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    private function gate(GateDecision $decision): Gate
    {
        return new readonly class($decision) implements Gate {
            public function __construct(private GateDecision $decision)
            {
            }

            public function handle(Request $request): GateDecision
            {
                return $this->decision;
            }
        };
    }

    /**
     * @param \ArrayObject<int, string> $calls
     */
    private function recordingGate(\ArrayObject $calls, string $name, GateDecision $decision): Gate
    {
        return new readonly class($calls, $name, $decision) implements Gate {
            /**
             * @param \ArrayObject<int, string> $calls
             */
            public function __construct(
                private \ArrayObject $calls,
                private string $name,
                private GateDecision $decision,
            ) {
            }

            public function handle(Request $request): GateDecision
            {
                $this->calls[] = $this->name;

                return $this->decision;
            }
        };
    }

    private function mainRequest(Request $request): RequestEvent
    {
        return new RequestEvent(
            kernel: $this->createStub(HttpKernelInterface::class),
            request: $request,
            requestType: HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
