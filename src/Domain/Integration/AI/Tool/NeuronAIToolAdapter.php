<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Infrastructure\Serialization\Json;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolOutput;
use NeuronAI\Tools\ToolProperty;

final class NeuronAIToolAdapter extends Tool
{
    public function __construct(
        private readonly AITool $tool,
        ?int $maxRuns,
    ) {
        $this->name = $tool->getName();
        $this->description = $tool->getDescription();
        $this->maxRuns = $maxRuns;
    }

    /**
     * @return \NeuronAI\Tools\ToolPropertyInterface[]
     */
    #[\Override]
    protected function properties(): array
    {
        return array_map(
            static fn (ToolParameter $parameter): ToolProperty => new ToolProperty(
                name: $parameter->getName(),
                type: match ($parameter->getType()) {
                    ToolParameterType::STRING => PropertyType::STRING,
                    ToolParameterType::INTEGER => PropertyType::INTEGER,
                },
                description: $parameter->getDescription(),
                required: $parameter->isRequired(),
            ),
            $this->tool->getParameters(),
        );
    }

    public function __invoke(mixed ...$arguments): ToolOutput
    {
        try {
            $result = ($this->tool)(new ToolArguments($arguments));
        } catch (ToolFailed $e) {
            return ToolOutput::error($e->getMessage());
        }

        return ToolOutput::text(Json::encode($result));
    }
}
