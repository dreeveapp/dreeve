<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Infrastructure\Security\Visitor;
use Twig\Attribute\AsTwigFunction;

final readonly class SecurityTwigExtension
{
    public function __construct(
        private Visitor $visitor,
    ) {
    }

    #[AsTwigFunction('isAuthenticated')]
    public function isAuthenticated(): bool
    {
        return $this->visitor->isAuthenticated();
    }
}
