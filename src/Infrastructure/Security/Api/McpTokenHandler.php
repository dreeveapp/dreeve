<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

final readonly class McpTokenHandler extends TokenHandler
{
    public function __construct(McpToken $token)
    {
        parent::__construct($token);
    }
}
