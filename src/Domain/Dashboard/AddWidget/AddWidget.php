<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\AddWidget;

use App\Domain\Dashboard\Widget\WidgetName;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;

#[RequiresRebuild]
final readonly class AddWidget extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private WidgetName $widgetName,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['widget']) || !is_string($payload['widget']) || '' === trim($payload['widget'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "widget" is required.');
        }

        return new self(
            widgetName: WidgetName::fromConfigValue(trim($payload['widget'])),
        );
    }

    public function getWidgetName(): WidgetName
    {
        return $this->widgetName;
    }
}
