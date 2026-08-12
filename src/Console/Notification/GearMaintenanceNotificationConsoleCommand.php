<?php

declare(strict_types=1);

namespace App\Console\Notification;

use App\Application\AppUrl;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: GearMaintenanceNotificationConsoleCommand::NAME, description: 'Send out gear maintenance notification', aliases: ['app:cron:gear-maintenance-notification'])]
final class GearMaintenanceNotificationConsoleCommand extends Command
{
    public const string NAME = 'app:notification:gear-maintenance';

    public function __construct(
        private readonly MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private readonly AppUrl $appUrl,
        private readonly CommandBus $commandBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->maintenanceTaskProgressCalculator->getGearIdsThatHaveDueTasks()->isEmpty()) {
            return Command::SUCCESS;
        }
        $this->commandBus->dispatch(new SendNotification(
            title: 'Gear maintenance is due',
            message: 'One of your gear components needs some love. Fix it up before it falls apart!',
            tags: ['hammer_and_wrench'],
            actionUrl: $this->appUrl,
        ));

        return Command::SUCCESS;
    }
}
