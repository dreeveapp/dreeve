<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\SaveDashboardLayout;

use App\Domain\Dashboard\DashboardLayoutRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class SaveDashboardLayoutCommandHandler implements CommandHandler
{
    public function __construct(
        private DashboardLayoutRepository $dashboardLayoutRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof SaveDashboardLayout);

        $this->dashboardLayoutRepository->saveLayout($command->getOrderedWidgets());
    }
}
