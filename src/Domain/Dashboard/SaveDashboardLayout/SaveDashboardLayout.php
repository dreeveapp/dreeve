<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\SaveDashboardLayout;

use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;
use App\Infrastructure\CQRS\Command\SuppressesFlashMessage;

#[RequiresRebuild]
#[SuppressesFlashMessage]
final readonly class SaveDashboardLayout extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    /**
     * @param list<array{id: string, width: int}> $orderedWidgets
     */
    private function __construct(
        private array $orderedWidgets,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $layout = $payload['layout'] ?? null;
        if (!is_array($layout) || [] === $layout || !array_is_list($layout)) {
            throw CouldNotDeserializeCommand::invalidPayload('A non-empty "layout" list is required.');
        }

        $orderedWidgets = [];
        foreach ($layout as $item) {
            if (!is_array($item) || !isset($item['id']) || !is_string($item['id']) || '' === trim($item['id'])) {
                throw CouldNotDeserializeCommand::invalidPayload('Each layout item requires a non-empty "id".');
            }

            if (!isset($item['width']) || !is_int($item['width']) || !in_array($item['width'], ConfiguredWidgets::WIDTHS, true)) {
                throw CouldNotDeserializeCommand::invalidPayload(sprintf('Each layout item requires a "width" that is one of [%s].', implode(', ', ConfiguredWidgets::WIDTHS)));
            }

            $orderedWidgets[] = ['id' => trim($item['id']), 'width' => $item['width']];
        }

        return new self($orderedWidgets);
    }

    /**
     * @return list<array{id: string, width: int}>
     */
    public function getOrderedWidgets(): array
    {
        return $this->orderedWidgets;
    }
}
