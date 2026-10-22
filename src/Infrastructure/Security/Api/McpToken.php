<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

final readonly class McpToken extends Token
{
    protected static function getPrefix(): string
    {
        return 'drvm_';
    }

    public function getRole(): string
    {
        return 'ROLE_MCP';
    }

    public function getUserIdentifier(): string
    {
        return 'dreeve-mcp';
    }
}
