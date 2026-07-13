<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Infrastructure\Http\Gate\ConditionalRedirectGate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ConditionalRedirectGateTest extends TestCase
{
    public function testItDefersWhenNotGuarding(): void
    {
        $gate = $this->gate(shouldGuard: false);

        $this->assertFalse($gate->handle(Request::create('/anything'))->hasBeenApplied());
    }

    public function testItRedirectsANonAllowedPath(): void
    {
        $gate = $this->gate(shouldGuard: true);

        $decision = $gate->handle(Request::create('/dashboard'));

        $this->assertTrue($decision->hasBeenApplied());
        $response = $decision->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/gate-target', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    #[DataProvider('provideAllowedPaths')]
    public function testItAllowsAllowedPathsAndTheRedirectTarget(string $path): void
    {
        $gate = $this->gate(shouldGuard: true);

        $decision = $gate->handle(Request::create($path));

        // The gate has been applied: it keeps this path open, so the gates that follow are no
        // longer consulted. They would redirect the user away from it.
        $this->assertTrue($decision->hasBeenApplied());
        $this->assertNull($decision->getResponse());
    }

    public static function provideAllowedPaths(): iterable
    {
        yield 'exact allowed path' => ['/allowed'];
        yield 'sub path of allowed prefix' => ['/allowed/deeper'];
        yield 'the redirect target itself (loop guard)' => ['/gate-target'];
        yield 'sub path of the redirect target' => ['/gate-target/step'];
    }

    public function testItMatchesOnlyAtSegmentBoundaries(): void
    {
        $gate = $this->gate(shouldGuard: true);

        // '/allowed' must not match '/allowedish'.
        $response = $gate->handle(Request::create('/allowedish'))->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/gate-target', $response->getTargetUrl());
    }

    private function gate(bool $shouldGuard): ConditionalRedirectGate
    {
        // The route name 'gate_target' resolves to '/gate-target'; the base class logic is
        // what's under test here, so the router is stubbed.
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/gate-target');

        return new class($urlGenerator, $shouldGuard) extends ConditionalRedirectGate {
            public function __construct(UrlGeneratorInterface $urlGenerator, private readonly bool $shouldGuard)
            {
                parent::__construct($urlGenerator);
            }

            protected function shouldGuard(): bool
            {
                return $this->shouldGuard;
            }

            protected function allowedPaths(): array
            {
                return ['/allowed'];
            }

            protected function redirectToRouteName(): string
            {
                return 'gate_target';
            }
        };
    }
}
