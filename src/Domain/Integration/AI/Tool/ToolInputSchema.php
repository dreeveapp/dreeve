<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

final readonly class ToolInputSchema
{
    /**
     * @param list<ToolParameter> $parameters
     */
    private function __construct(
        private array $parameters,
    ) {
    }

    /**
     * @param list<ToolParameter> $parameters
     */
    public static function fromParameters(array $parameters): self
    {
        return new self($parameters);
    }

    public function hasParameters(): bool
    {
        return [] !== $this->parameters;
    }

    /**
     * @return array{type: 'object', properties: array<string, array{type: string, description: string}>, required: list<string>, additionalProperties: false}
     */
    public function toJsonSchema(): array
    {
        $properties = [];
        $required = [];
        foreach ($this->parameters as $parameter) {
            $properties[$parameter->getName()] = $parameter->toJsonSchema();
            if ($parameter->isRequired()) {
                $required[] = $parameter->getName();
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }
}
