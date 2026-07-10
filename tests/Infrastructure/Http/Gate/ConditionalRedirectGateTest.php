<?php

namespace App\Tests\Infrastructure\Http\Gate;

use App\Infrastructure\Http\Gate\ConditionalRedirectGate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ConditionalRedirectGateTest extends TestCase
{
    public function testItPassesThroughWhenNotGuarding(): void
    {
        $gate = $this->gate(shouldGuard: false);

        $this->assertNull($gate->handle(Request::create('/anything')));
    }

    public function testItRedirectsANonAllowedPath(): void
    {
        $gate = $this->gate(shouldGuard: true);

        $response = $gate->handle(Request::create('/dashboard'));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/gate-target', $response->getTargetUrl());
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    #[DataProvider('provideAllowedPaths')]
    public function testItAllowsAllowedPathsAndTheRedirectTarget(string $path): void
    {
        $gate = $this->gate(shouldGuard: true);

        $this->assertNull($gate->handle(Request::create($path)));
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
        $response = $gate->handle(Request::create('/allowedish'));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/gate-target', $response->getTargetUrl());
    }

    private function gate(bool $shouldGuard): ConditionalRedirectGate
    {
        return new class($shouldGuard) extends ConditionalRedirectGate {
            public function __construct(private readonly bool $shouldGuard)
            {
            }

            protected function shouldGuard(): bool
            {
                return $this->shouldGuard;
            }

            protected function allowedPaths(): array
            {
                return ['/allowed'];
            }

            protected function redirectTo(): string
            {
                return '/gate-target';
            }
        };
    }
}
