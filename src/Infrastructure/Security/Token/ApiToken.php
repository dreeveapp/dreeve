<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Token;

final readonly class ApiToken extends Token
{
    protected static function getPrefix(): string
    {
        return 'drv_';
    }

    public function getRole(): string
    {
        return 'ROLE_API';
    }

    public function getUserIdentifier(): string
    {
        return 'dreeve-api';
    }
}
