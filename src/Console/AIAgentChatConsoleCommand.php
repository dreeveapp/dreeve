<?php

declare(strict_types=1);

namespace App\Console;

use App\Domain\Integration\AI\Chat\AddChatMessage\AddChatMessage;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @codeCoverageIgnore
 */
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: 'app:ai:agent-chat', description: 'Start a new AI agent chat')]
final class AIAgentChatConsoleCommand extends Command
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly SettingsRepository $settingsRepository,
        private readonly CommandBus $commandBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->settingsRepository->integrations()->isAIIntegrationEnabled()) {
            $io->error('The AI feature is not enabled.');

            return Command::SUCCESS;
        }

        $helper = $this->getHelper('question');
        assert($helper instanceof QuestionHelper);

        $io->block(
            messages: [
                "Hi 👋, I'm Mark, your personal workout assistant.".PHP_EOL.
                'Feel free to ask me anything—whether it’s about your training history or tips to improve!',
                'Type "exit" to close the conversation.',
            ],
            style: 'fg=black;bg=green',
            padding: true
        );

        $conversation = new MessageBag();
        while (true) {
            $question = new Question('<info><You></info> ');
            $userInput = $helper->ask($input, $output, $question);

            if (null === $userInput) {
                continue; // if the user just presses Enter
            }

            if ('exit' === $userInput) {
                $output->writeln('<comment><Mark></comment> Mkey, bye 👋');
                break;
            }

            $conversation->add(Message::ofUser($userInput));
            $this->commandBus->dispatch(new AddChatMessage(
                message: $userInput,
                messageRole: Role::User,
            ));

            try {
                $answer = '';
                $execution = $this->agent->call($conversation, ['stream' => true]);
                foreach ($execution->asStream() as $delta) {
                    if (!$delta instanceof TextDelta) {
                        continue;
                    }
                    if ('' === $answer) {
                        $output->write('<comment><Mark></comment> ');
                    }
                    $answer .= $delta->getText();
                    $output->write($delta->getText());
                }
                $output->writeln('');
            } catch (\Exception $e) {
                $output->writeln('');
                $output->writeln('<comment><Mark></comment> Oh no, I made a booboo...');
                $output->writeln(sprintf(
                    '<error>%s</error>',
                    $e->getMessage()
                ));

                break;
            }

            $conversation->add(Message::ofAssistant($answer));
            $this->commandBus->dispatch(new AddChatMessage(
                message: $answer,
                messageRole: Role::Assistant,
            ));
        }

        return Command::SUCCESS;
    }
}
