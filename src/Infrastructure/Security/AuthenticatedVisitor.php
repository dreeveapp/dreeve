<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class AuthenticatedVisitor
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->security->getUser() instanceof UserInterface;
    }
}
