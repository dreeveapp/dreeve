<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Chat;

use App\Domain\Integration\AI\Chat\AddChatMessage\AddChatMessage;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\History\InMemoryMessageStore;
use NeuronAI\Chat\Messages\Message;

/**
 * @codeCoverageIgnore
 */
final class SFSMessageStore extends InMemoryMessageStore
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    #[\Override]
    public function append(string $threadId, Message $message): void
    {
        if (isset($this->threads[$threadId][$message->getId()])) {
            return;
        }

        parent::append($threadId, $message);

        if (!in_array($message->getContent(), [null, '', '0'], true)) {
            $this->commandBus->dispatch(new AddChatMessage(
                message: $message->getContent(),
                messageRole: MessageRole::from($message->getRole()),
            ));
        }
    }
}
