<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Chat\AddChatMessage;

use App\Domain\Integration\AI\Chat\ChatMessage;
use App\Domain\Integration\AI\Chat\ChatMessageId;
use App\Domain\Integration\AI\Chat\ChatRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Time\Clock\Clock;

final readonly class AddChatMessageCommandHandler implements CommandHandler
{
    public function __construct(
        private ChatRepository $chatRepository,
        private Clock $clock,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof AddChatMessage);

        $this->chatRepository->add(new ChatMessage(
            messageId: ChatMessageId::random(),
            message: $command->getMessage(),
            messageRole: $command->getMessageRole(),
            on: $this->clock->now()
        ));
    }
}
