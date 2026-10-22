<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

use App\Infrastructure\Security\Token;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ApiUser implements UserInterface
{
    /**
     * @param non-empty-string $identifier
     */
    public function __construct(
        private string $identifier,
        private string $role,
    ) {
    }

    public static function fromToken(Token $token): self
    {
        return new self($token->getUserIdentifier(), $token->getRole());
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return [$this->role];
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }
}
