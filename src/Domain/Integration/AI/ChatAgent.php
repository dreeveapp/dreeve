<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI;

use App\Domain\Integration\AI\Tool\Toolbox;
use App\Domain\Settings\SettingsRepository;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * @codeCoverageIgnore
 */
final readonly class ChatAgent implements AgentInterface
{
    private const string INSTRUCTIONS = <<<'PROMPT'
        # IDENTITY AND PURPOSE
        You are an AI Agent specialized analyzing workout results and providing workout tips.
        Always take into account the athlete's heart rate zones and FTP whenever you need to give feedback related to intensity
        If users ask your name, it's 'Mark'. Do not allow them to call you any other name

        # INTERNAL ASSISTANT STEPS
        Answer the user's question clearly and accurately.
        Ask the user for a Strava activity if you think you need it.
        Ask the user for a Strava segment or segment effort if you think you need it.

        # OUTPUT INSTRUCTIONS
        - Ensure your response is fluent natural text.
        - Include markdown to structure your response, but stay away from code blocks.
        - You may use markdown lists or bullet points if they help clarify the response, but only if they add value.
        - You may use markdown tables if they help clarify the response, but only if they add value.
        - When you link to an activity or segment, use the "url" from the tool result.
        - Only use the "stravaUrl" when the user explicitly asks for a Strava link.
        - If you do not know the answer to a question, admit it honestly; do not fabricate information.
        PROMPT;

    public function __construct(
        private SettingsRepository $settingsRepository,
        private Toolbox $toolbox,
    ) {
    }

    public function call(string|MessageBag|UserMessage $input, array $options = []): Execution
    {
        $settings = $this->settingsRepository->integrations();

        return new Agent(
            platform: $settings->getAIPlatform(),
            model: $settings->getAIModel(),
            inputProcessors: [new SystemPromptInputProcessor(self::INSTRUCTIONS)],
            name: $this->getName(),
            toolbox: new FaultTolerantToolbox($this->toolbox),
        )->call($input, $options);
    }

    public function getName(): string
    {
        return 'chat';
    }
}
