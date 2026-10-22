<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\MCP;

use App\Domain\Integration\AI\Tool\AITool;
use App\Domain\Integration\AI\Tool\ToolArguments;
use App\Domain\Integration\AI\Tool\ToolFailed;
use Mcp\Exception\ToolCallException;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;

final readonly class McpToolAdapter implements ToolHandlerInterface
{
    public function __construct(
        private AITool $tool,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<mixed>
     */
    public function execute(array $arguments, ClientGateway $gateway): array
    {
        try {
            return ($this->tool)(new ToolArguments($arguments));
        } catch (ToolFailed $e) {
            throw new ToolCallException($e->getMessage());
        }
    }
}
