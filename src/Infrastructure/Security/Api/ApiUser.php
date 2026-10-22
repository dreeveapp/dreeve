<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ApiUser implements UserInterface
{
    public function __construct(
        private TokenType $tokenType,
    ) {
    }

    public function getTokenType(): TokenType
    {
        return $this->tokenType;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return [$this->tokenType->getRole()];
    }

    public function getUserIdentifier(): string
    {
        return $this->tokenType->getUserIdentifier();
    }
}
