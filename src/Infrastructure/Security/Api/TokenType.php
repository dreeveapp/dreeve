<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

enum TokenType: string
{
    case API = 'api';

    public function getPrefix(): string
    {
        return match ($this) {
            self::API => 'drv_',
        };
    }

    public function getRole(): string
    {
        return match ($this) {
            self::API => 'ROLE_API',
        };
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return 'dreeve-'.$this->value;
    }
}
