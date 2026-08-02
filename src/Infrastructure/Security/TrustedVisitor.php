<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Config\DemoMode;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class TrustedVisitor
{
    public function __construct(
        private DemoMode $demoMode,
        private Security $security,
    ) {
    }

    public function isTrusted(): bool
    {
        if (!$this->demoMode->isEnabled()) {
            return true;
        }

        return $this->security->getUser() instanceof UserInterface;
    }
}
