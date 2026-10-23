<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

final readonly class ToolParameter
{
    private function __construct(
        private string $name,
        private ToolParameterType $type,
        private string $description,
        private bool $required,
    ) {
    }

    public static function string(string $name, string $description, bool $required = false): self
    {
        return new self(
            name: $name,
            type: ToolParameterType::STRING,
            description: $description,
            required: $required,
        );
    }

    public static function integer(string $name, string $description, bool $required = false): self
    {
        return new self(
            name: $name,
            type: ToolParameterType::INTEGER,
            description: $description,
            required: $required,
        );
    }

    public static function boolean(string $name, string $description, bool $required = false): self
    {
        return new self(
            name: $name,
            type: ToolParameterType::BOOLEAN,
            description: $description,
            required: $required,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ToolParameterType
    {
        return $this->type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return array{type: string, description: string}
     */
    public function toJsonSchema(): array
    {
        return [
            'type' => $this->type->value,
            'description' => $this->description,
        ];
    }
}
