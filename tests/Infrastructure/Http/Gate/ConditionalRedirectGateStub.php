<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http\Gate;

use App\Infrastructure\Http\Gate\ConditionalRedirectGate;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ConditionalRedirectGateStub extends ConditionalRedirectGate
{
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        private readonly bool $shouldGuard,
    ) {
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
}
