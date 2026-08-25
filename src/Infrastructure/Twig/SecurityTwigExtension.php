<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Infrastructure\Security\AuthenticatedVisitor;
use Twig\Attribute\AsTwigFunction;

final readonly class SecurityTwigExtension
{
    public function __construct(
        private AuthenticatedVisitor $authenticatedVisitor,
    ) {
    }

    #[AsTwigFunction('isAuthenticated')]
    public function isAuthenticated(): bool
    {
        return $this->authenticatedVisitor->isAuthenticated();
    }
}
