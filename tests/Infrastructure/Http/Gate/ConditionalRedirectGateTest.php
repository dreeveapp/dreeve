<?php

namespace App\Tests\Infrastructure\Http\Gate;

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
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/gate-target');
        $gate = new ConditionalRedirectGateStub($urlGenerator, shouldGuard: false);

        $this->assertFalse($gate->handle(Request::create('/anything'))->hasBeenApplied());
    }

    public function testItRedirectsANonAllowedPath(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/gate-target');
        $gate = new ConditionalRedirectGateStub($urlGenerator, shouldGuard: true);

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
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/gate-target');
        $gate = new ConditionalRedirectGateStub($urlGenerator, shouldGuard: true);

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
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/gate-target');
        $gate = new ConditionalRedirectGateStub($urlGenerator, shouldGuard: true);

        // '/allowed' must not match '/allowedish'.
        $response = $gate->handle(Request::create('/allowedish'))->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/gate-target', $response->getTargetUrl());
    }
}
