<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Infrastructure\Http\Gate\Gate;
use App\Infrastructure\Http\Gate\GateRequestListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class GateRequestListenerTest extends TestCase
{
    public function testItSetsTheResponseOfTheFirstInterceptingGate(): void
    {
        $redirect = new RedirectResponse('/gated');
        $listener = new GateRequestListener([
            $this->gate(null),
            $this->gate($redirect),
            $this->gate(new RedirectResponse('/never-reached')),
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
            $this->recordingGate($calls, 'first', null),
            $this->recordingGate($calls, 'second', new RedirectResponse('/gated')),
            $this->recordingGate($calls, 'third', new RedirectResponse('/never-reached')),
        ]);

        $event = $this->mainRequest(Request::create('/dashboard'));
        $listener->onKernelRequest($event);

        // The third gate is never reached once the second one intercepts.
        $this->assertSame(['first', 'second'], $calls->getArrayCopy());
        $this->assertSame('/gated', $event->getResponse()?->getTargetUrl());
    }

    public function testItLetsTheRequestThroughWhenNoGateIntercepts(): void
    {
        $listener = new GateRequestListener([$this->gate(null), $this->gate(null)]);

        $event = $this->mainRequest(Request::create('/dashboard'));
        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    #[DataProvider('provideAlwaysOpenPaths')]
    public function testItNeverGatesAlwaysOpenPaths(string $path): void
    {
        $listener = new GateRequestListener([$this->gate(new RedirectResponse('/gated'))]);

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
        $listener = new GateRequestListener([$this->gate(new RedirectResponse('/gated'))]);

        $event = new RequestEvent(
            kernel: $this->createStub(HttpKernelInterface::class),
            request: Request::create('/dashboard'),
            requestType: HttpKernelInterface::SUB_REQUEST,
        );
        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    private function gate(?Response $response): Gate
    {
        return new readonly class($response) implements Gate {
            public function __construct(private ?Response $response)
            {
            }

            public function handle(Request $request): ?Response
            {
                return $this->response;
            }
        };
    }

    /**
     * @param \ArrayObject<int, string> $calls
     */
    private function recordingGate(\ArrayObject $calls, string $name, ?Response $response): Gate
    {
        return new readonly class($calls, $name, $response) implements Gate {
            /**
             * @param \ArrayObject<int, string> $calls
             */
            public function __construct(
                private \ArrayObject $calls,
                private string $name,
                private ?Response $response,
            ) {
            }

            public function handle(Request $request): ?Response
            {
                $this->calls[] = $this->name;

                return $this->response;
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
