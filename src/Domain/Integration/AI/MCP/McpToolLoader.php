<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\MCP;

use App\Domain\Integration\AI\Tool\AITool;
use Mcp\Capability\Registry\Loader\ExplicitElementLoader;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class McpToolLoader implements LoaderInterface
{
    /**
     * @param iterable<AITool> $tools
     */
    public function __construct(
        #[AutowireIterator('app.ai_tool')]
        private iterable $tools,
    ) {
    }

    public function load(RegistryInterface $registry): void
    {
        $tools = [];
        foreach ($this->tools as $tool) {
            $properties = [];
            $required = [];
            foreach ($tool->getParameters() as $parameter) {
                $properties[$parameter->getName()] = $parameter->toJsonSchema();
                if ($parameter->isRequired()) {
                    $required[] = $parameter->getName();
                }
            }

            $tools[] = [
                'definition' => new Tool(
                    name: $tool->getName(),
                    title: null,
                    inputSchema: [
                        'type' => 'object',
                        'properties' => [] === $properties ? new \stdClass() : $properties,
                        'required' => $required,
                    ],
                    description: $tool->getDescription(),
                    annotations: new ToolAnnotations(readOnlyHint: true),
                ),
                'handler' => new McpToolAdapter($tool),
            ];
        }

        new ExplicitElementLoader(tools: $tools)->load($registry);
    }
}
