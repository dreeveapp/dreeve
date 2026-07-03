<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\ConfigureWidget;

use App\Domain\Dashboard\DashboardLayoutRepository;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\ConfiguredWidget;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;

final readonly class ConfigureWidgetCommandHandler implements CommandHandler
{
    public function __construct(
        private ConfiguredWidgets $configuredWidgets,
        private DashboardLayoutRepository $dashboardLayoutRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof ConfigureWidget);

        $configuredWidget = $this->configuredWidgets->find($command->getDashboardWidgetId());
        if (!$configuredWidget instanceof ConfiguredWidget) {
            throw new \RuntimeException(sprintf('Dashboard widget "%s" does not exist.', $command->getDashboardWidgetId()));
        }

        $configuration = $this->coerce(
            defaults: $configuredWidget->getWidget()->getDefaultConfiguration(),
            submitted: $command->getConfiguration(),
        );

        try {
            $configuredWidget->getWidget()->guardValidConfiguration($configuration);
        } catch (InvalidDashboardLayout $e) {
            throw CouldNotProcessCommand::withReason($e->getMessage());
        }

        $this->dashboardLayoutRepository->updateWidgetConfiguration(
            dashboardWidgetId: $command->getDashboardWidgetId(),
            configuration: $configuration->toArray(),
        );
    }

    /**
     * @param array<string, mixed> $submitted
     */
    private function coerce(WidgetConfiguration $defaults, array $submitted): WidgetConfiguration
    {
        $configuration = WidgetConfiguration::empty();

        foreach ($defaults->toArray() as $key => $default) {
            $configuration->add($key, match (true) {
                is_bool($default) => filter_var($submitted[$key] ?? false, FILTER_VALIDATE_BOOLEAN),
                is_int($default) => array_key_exists($key, $submitted) ? (int) $submitted[$key] : $default,
                is_float($default) => array_key_exists($key, $submitted) ? (float) $submitted[$key] : $default,
                is_array($default) => (array) ($submitted[$key] ?? []),
                is_string($default) => array_key_exists($key, $submitted) ? (string) $submitted[$key] : $default,
                // Null default: treat as a nullable string, empty means "unset".
                default => '' === trim((string) ($submitted[$key] ?? '')) ? null : (string) $submitted[$key],
            });
        }

        return $configuration;
    }
}
