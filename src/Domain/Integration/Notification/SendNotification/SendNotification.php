<?php

declare(strict_types=1);

namespace App\Domain\Integration\Notification\SendNotification;

use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\String\Url;

final readonly class SendNotification extends DomainCommand
{
    public function __construct(
        private string $title,
        private string $message,
        /** @var array<string> */
        private array $tags,
        private ?Url $actionUrl,
        private string $actionLabel = 'Open app',
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getActionUrl(): ?Url
    {
        return $this->actionUrl;
    }

    public function getActionLabel(): string
    {
        return $this->actionLabel;
    }
}
