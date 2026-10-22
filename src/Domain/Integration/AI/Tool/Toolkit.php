<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use NeuronAI\Tools\Toolkits\AbstractToolkit;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class Toolkit extends AbstractToolkit
{
    private const array MAX_RUNS = [
        'get_activity_by_id' => 25,
        'get_activity_laps' => 25,
        'get_activity_segments_efforts' => 25,
        'get_activity_splits' => 25,
        'get_activity_streams' => 25,
    ];

    /**
     * @param iterable<AITool> $tools
     */
    public function __construct(
        #[AutowireIterator('app.ai_tool')]
        private readonly iterable $tools,
    ) {
    }

    /**
     * @return \NeuronAI\Tools\ToolInterface[]
     */
    public function provide(): array
    {
        $tools = [];
        foreach ($this->tools as $tool) {
            $tools[] = new NeuronAIToolAdapter($tool, self::MAX_RUNS[$tool->getName()] ?? null);
        }

        return $tools;
    }
}
