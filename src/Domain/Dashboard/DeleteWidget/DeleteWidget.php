<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DeleteWidget;

use App\Domain\Dashboard\DashboardWidgetId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;

#[RequiresRebuild]
final readonly class DeleteWidget extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private DashboardWidgetId $dashboardWidgetId,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['dashboardWidgetId']) || !is_string($payload['dashboardWidgetId']) || '' === trim($payload['dashboardWidgetId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "dashboardWidgetId" is required.');
        }

        return new self(
            dashboardWidgetId: DashboardWidgetId::fromString(trim($payload['dashboardWidgetId'])),
        );
    }

    public function getDashboardWidgetId(): DashboardWidgetId
    {
        return $this->dashboardWidgetId;
    }
}
