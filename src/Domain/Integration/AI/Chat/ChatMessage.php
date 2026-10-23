<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Chat;

use App\Application\ProfilePictureUrl;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\AI\Platform\Message\Role;

#[ORM\Entity]
#[ORM\Index(name: 'ChatMessage_on', columns: ['on'])]
final class ChatMessage
{
    private ?ProfilePictureUrl $userProfilePictureUrl = null;
    private ?string $firstLetterOfFirstName = null;

    public function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        private readonly ChatMessageId $messageId,
        #[ORM\Column(type: 'text')]
        private readonly string $message,
        #[ORM\Column(type: 'string')]
        private readonly Role $messageRole,
        #[ORM\Column(type: 'datetime_immutable')]
        private readonly SerializableDateTime $on,
    ) {
    }

    public function getMessageId(): ChatMessageId
    {
        return $this->messageId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMessageRole(): Role
    {
        return $this->messageRole;
    }

    public function getOn(): SerializableDateTime
    {
        return $this->on;
    }

    public function withUserProfilePictureUrl(?ProfilePictureUrl $profilePictureUrl): self
    {
        $this->userProfilePictureUrl = $profilePictureUrl;

        return $this;
    }

    public function getUserProfilePictureUrl(): ?ProfilePictureUrl
    {
        return $this->userProfilePictureUrl;
    }

    public function withFirstLetterOfFirstName(string $firstLetterOfFirstName): self
    {
        $this->firstLetterOfFirstName = $firstLetterOfFirstName;

        return $this;
    }

    public function getFirstLetterOfFirstName(): ?string
    {
        return $this->firstLetterOfFirstName;
    }

    public function isUserMessage(): bool
    {
        return Role::User === $this->messageRole;
    }
}
