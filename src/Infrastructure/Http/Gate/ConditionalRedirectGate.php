<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Gate;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class ConditionalRedirectGate implements Gate
{
    abstract protected function shouldGuard(): bool;

    /**
     * @return list<string>
     */
    abstract protected function allowedPaths(): array;

    abstract protected function redirectTo(): string;

    final public function handle(Request $request): ?Response
    {
        if (!$this->shouldGuard()) {
            return null;
        }

        $path = $request->getPathInfo();
        foreach ([...$this->allowedPaths(), $this->redirectTo()] as $allowed) {
            if ($this->matches($path, $allowed)) {
                return null;
            }
        }

        return new RedirectResponse($this->redirectTo(), Response::HTTP_FOUND);
    }

    private function matches(string $path, string $allowed): bool
    {
        return $path === $allowed
            || str_starts_with($path, rtrim($allowed, '/').'/');
    }
}
