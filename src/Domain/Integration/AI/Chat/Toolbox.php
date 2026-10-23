<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Chat;

use App\Domain\Integration\AI\Tool\AITool;
use App\Domain\Integration\AI\Tool\ToolArguments;
use App\Domain\Integration\AI\Tool\ToolFailed;
use App\Domain\Integration\AI\Tool\ToolInputSchema;
use App\Infrastructure\Serialization\Json;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class Toolbox implements ToolboxInterface
{
    /**
     * @param iterable<AITool> $tools
     */
    public function __construct(
        #[AutowireIterator('app.ai_tool')]
        private iterable $tools,
    ) {
    }

    /**
     * @return Tool[]
     */
    public function getTools(): array
    {
        $definitions = [];
        foreach ($this->tools as $tool) {
            $inputSchema = ToolInputSchema::fromParameters($tool->getParameters());

            $definitions[] = new Tool(
                reference: new ExecutionReference($tool::class),
                name: $tool->getName(),
                description: $tool->getDescription(),
                parameters: $inputSchema->hasParameters() ? $inputSchema->toJsonSchema() : null,
            );
        }

        return $definitions;
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() !== $toolCall->getName()) {
                continue;
            }

            try {
                return new ToolResult($toolCall, Json::encode($tool(new ToolArguments($toolCall->getArguments()))));
            } catch (ToolFailed $e) {
                return new ToolResult($toolCall, $e->getMessage());
            }
        }

        throw ToolNotFoundException::notFoundForToolCall($toolCall);
    }
}
