<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Gate;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class ConditionalRedirectGate implements Gate
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    abstract protected function shouldGuard(): bool;

    /**
     * @return list<string>
     */
    abstract protected function allowedPaths(): array;

    abstract protected function redirectToRouteName(): string;

    final public function handle(Request $request): GateDecision
    {
        if (!$this->shouldGuard()) {
            return GateDecision::defer();
        }

        $target = $this->urlGenerator->generate($this->redirectToRouteName());

        $path = $request->getPathInfo();
        foreach ([...$this->allowedPaths(), $target] as $allowed) {
            if ($this->matches($path, $allowed)) {
                return GateDecision::allow();
            }
        }

        return GateDecision::respond(new RedirectResponse($target, Response::HTTP_FOUND));
    }

    private function matches(string $path, string $allowed): bool
    {
        return $path === $allowed
            || str_starts_with($path, rtrim($allowed, '/').'/');
    }
}
