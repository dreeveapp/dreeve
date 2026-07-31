<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Config\DemoMode;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * With demo mode disabled every visitor is trusted. With it enabled only a logged-in one is,
 * everybody else gets anonymized data.
 */
final readonly class TrustedVisitor
{
    public function __construct(
        private DemoMode $demoMode,
        private Security $security,
    ) {
    }

    public function isTrusted(): bool
    {
        return !$this->demoMode->isEnabled() || $this->security->getUser() instanceof UserInterface;
    }
}
