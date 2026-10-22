<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

final readonly class ApiTokenHandler extends TokenHandler
{
    public function __construct(ApiToken $token)
    {
        parent::__construct($token);
    }
}
