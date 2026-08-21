<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ApiUser implements UserInterface
{
    public const string IDENTIFIER = 'dreeve-api';
    public const string ROLE = 'ROLE_API';

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return [self::ROLE];
    }

    public function getUserIdentifier(): string
    {
        return self::IDENTIFIER;
    }
}
