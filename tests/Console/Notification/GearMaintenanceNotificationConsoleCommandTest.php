<?php

namespace App\Tests\Console\Notification;

use App\Application\AppUrl;
use App\Console\Notification\GearMaintenanceNotificationConsoleCommand;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Gear\Maintenance\GearComponentBuilder;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class GearMaintenanceNotificationConsoleCommandTest extends ConsoleCommandTestCase
{
    private GearMaintenanceNotificationConsoleCommand $command;
    private SpyCommandBus $commandBus;

    public function testNotifiesWhenGearMaintenanceIsDue(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('g10130856'))
            ->build();
        $this->getContainer()->get(GearRepository::class)->add($gear);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withName(Name::fromString('chain-lubed'))
                ->withGearId($gear->getId())
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId($gear->getId())
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-02 00:00:00'))
                ->withDistance(Kilometer::from(600))
                ->build(),
            []
        ));

        $this->getContainer()->get(GearMaintenanceLogRepository::class)->add(GearMaintenanceLog::create(
            gearId: $gear->getId(),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        ));

        $command = $this->getCommandInApplication('app:notification:gear-maintenance');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEquals(
            [new SendNotification(
                title: 'Gear maintenance is due',
                message: 'One of your gear components needs some love. Fix it up before it falls apart!',
                tags: ['hammer_and_wrench'],
                actionUrl: AppUrl::fromString('http://localhost'),
            )],
            $this->commandBus->getDispatchedCommands(),
        );
    }

    public function testDoesNotNotifyWhenNoMaintenanceIsDue(): void
    {
        $command = $this->getCommandInApplication('app:notification:gear-maintenance');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($this->commandBus->getDispatchedCommands());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: true,
        );
        $this->getContainer()->get(GearComponentRepository::class)->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('chain'))
            ->withLabel('Some cool chain')
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g10130856')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
                label: Name::fromString('Lube'),
                intervalValue: 500,
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
            ))
            ->build());

        $this->command = new GearMaintenanceNotificationConsoleCommand(
            $this->getContainer()->get(MaintenanceTaskProgressCalculator::class),
            AppUrl::fromString('http://localhost'),
            $this->commandBus = new SpyCommandBus(),
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->command;
    }
}
