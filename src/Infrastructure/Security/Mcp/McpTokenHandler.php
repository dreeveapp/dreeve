<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Mcp;

use App\Infrastructure\Security\TokenHandler;

final readonly class McpTokenHandler extends TokenHandler
{
    public function __construct(McpToken $token)
    {
        parent::__construct($token);
    }
}
