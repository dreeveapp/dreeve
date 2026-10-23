<?php

declare(strict_types=1);

namespace App\Tests\Domain\Integration\AI\Chat;

use App\Application\ProfilePictureUrl;
use App\Domain\Integration\AI\Chat\ChatMessage;
use App\Domain\Integration\AI\Chat\ChatMessageId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\AI\Platform\Message\Role;

final class ChatMessageBuilder
{
    private ChatMessageId $messageId;
    private string $message = 'Cool';
    private Role $messageRole = Role::User;
    private readonly SerializableDateTime $on;
    private readonly ?ProfilePictureUrl $userProfilePictureUrl;
    private ?string $firstLetterOfFirstName = 'r';

    public function __construct()
    {
        $this->messageId = ChatMessageId::fromUnprefixed('test');
        $this->on = SerializableDateTime::fromString('2025-01-01 15:56:00');
        $this->userProfilePictureUrl = null;
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): ChatMessage
    {
        return new ChatMessage(
            messageId: $this->messageId,
            message: $this->message,
            messageRole: $this->messageRole,
            on: $this->on,
        )->withFirstLetterOfFirstName($this->firstLetterOfFirstName)
            ->withUserProfilePictureUrl($this->userProfilePictureUrl);
    }

    public function withMessageId(ChatMessageId $messageId): self
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function withMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function withMessageRole(Role $messageRole): self
    {
        $this->messageRole = $messageRole;

        return $this;
    }

    public function withFirstLetterOfFirstName(string $firstLetter): self
    {
        $this->firstLetterOfFirstName = $firstLetter;

        return $this;
    }
}
