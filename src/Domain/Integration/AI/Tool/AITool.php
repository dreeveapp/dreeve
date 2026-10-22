<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.ai_tool')]
interface AITool
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @return list<ToolParameter>
     */
    public function getParameters(): array;

    /**
     * @return array<mixed>
     */
    public function __invoke(ToolArguments $arguments): array;
}
