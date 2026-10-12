<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Config\DemoMode;

final readonly class TrustedVisitor
{
    public function __construct(
        private DemoMode $demoMode,
        private AuthenticatedVisitor $authenticatedVisitor,
    ) {
    }

    public function isTrusted(): bool
    {
        if (!$this->demoMode->isEnabled()) {
            return true;
        }

        return $this->authenticatedVisitor->isAuthenticated();
    }
}
