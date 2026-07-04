<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\AddWidget;

use App\Domain\Dashboard\DashboardLayoutRepository;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;

final readonly class AddWidgetCommandHandler implements CommandHandler
{
    public function __construct(
        private ConfiguredWidgets $configuredWidgets,
        private DashboardLayoutRepository $dashboardLayoutRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof AddWidget);

        $widgetName = $command->getWidgetName();
        if (!$this->configuredWidgets->hasAvailableWidget($widgetName)) {
            throw CouldNotProcessCommand::withReason(sprintf('Dashboard widget "%s" does not exist.', $widgetName));
        }

        $this->dashboardLayoutRepository->addWidget(
            dashboardWidgetId: DashboardWidgetId::random(),
            widgetName: $widgetName,
            width: 50,
        );
    }
}
