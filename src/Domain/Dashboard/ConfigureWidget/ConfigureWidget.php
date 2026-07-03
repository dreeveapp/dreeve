<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\ConfigureWidget;

use App\Domain\Dashboard\DashboardWidgetId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;

#[RequiresRebuild]
final readonly class ConfigureWidget extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    /**
     * @param array<string, mixed> $configuration
     */
    private function __construct(
        private DashboardWidgetId $dashboardWidgetId,
        private array $configuration,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['dashboardWidgetId']) || !is_string($payload['dashboardWidgetId']) || '' === trim($payload['dashboardWidgetId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "dashboardWidgetId" is required.');
        }

        $configuration = $payload['config'] ?? [];
        if (!is_array($configuration)) {
            throw CouldNotDeserializeCommand::invalidPayload('The "config" must be an object.');
        }

        return new self(
            dashboardWidgetId: DashboardWidgetId::fromString(trim($payload['dashboardWidgetId'])),
            configuration: $configuration,
        );
    }

    public function getDashboardWidgetId(): DashboardWidgetId
    {
        return $this->dashboardWidgetId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }
}
