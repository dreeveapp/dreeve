<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Gear\Gear;
use App\Domain\Gear\GearRepository;

final readonly class GetGear implements AITool
{
    public function __construct(
        private GearRepository $gearRepository,
    ) {
    }

    public function getName(): string
    {
        return 'get_gear';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves the athlete’s gear information from the database, including name, type, and distance.
            Use this tool when the user asks about equipment such as shoes, bikes, or other gear.
            It provides the details needed to track usage or summarize performance by equipment.
            Example requests include “Show my bike details” or “How many kilometers have I run with my shoes?”
            DESC;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        return $this->gearRepository->findAllUsed()->map(static fn (Gear $gear): array => $gear->exportForAITooling());
    }
}
