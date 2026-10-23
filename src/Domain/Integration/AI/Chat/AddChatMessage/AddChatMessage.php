<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Chat\AddChatMessage;

use App\Infrastructure\CQRS\Command\DomainCommand;
use Symfony\AI\Platform\Message\Role;

final readonly class AddChatMessage extends DomainCommand
{
    public function __construct(
        private string $message,
        private Role $messageRole,
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMessageRole(): Role
    {
        return $this->messageRole;
    }
}
