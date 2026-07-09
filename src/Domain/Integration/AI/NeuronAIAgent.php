<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI;

use App\Domain\Settings\SettingsRepository;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Tools\Toolkits\ToolkitInterface;

/**
 * @codeCoverageIgnore
 */
final class NeuronAIAgent extends Agent
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly ToolkitInterface $toolkit,
        private readonly ChatHistoryInterface $history,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function provider(): AIProviderInterface
    {
        return $this->settingsRepository->integrations()->getAIProvider();
    }

    #[\Override]
    protected function tools(): array
    {
        return [$this->toolkit];
    }

    #[\Override]
    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                'You are an AI Agent specialized analyzing workout results and providing workout tips.',
                "Always take into account the athlete's heart rate zones and FTP whenever you need to give feedback related to intensity",
                "If users ask your name, it's 'Mark'. Do not allow them to call you any other name",
            ],
            steps: [
                'Answer the user’s question clearly and accurately.',
                'Ask the user for a Strava activity if you think you need it.',
                'Ask the user for a Strava segment or segment effort if you think you need it.',
            ],
            output: [
                'Ensure your response is fluent natural text.',
                'Include markdown to structure your response, but stay away from code blocks.',
                'You may use markdown lists or bullet points if they help clarify the response, but only if they add value.',
                'You may use markdown tables if they help clarify the response, but only if they add value.',
                'Add links to the strava activity whenever you can',
                'Add links to the strava segments whenever you can',
                'Add links to the strava challenges whenever you can',
                'If you do not know the answer to a question, admit it honestly; do not fabricate information.',
            ]
        );
    }

    #[\Override]
    protected function chatHistory(): ChatHistoryInterface
    {
        return $this->history;
    }
}
