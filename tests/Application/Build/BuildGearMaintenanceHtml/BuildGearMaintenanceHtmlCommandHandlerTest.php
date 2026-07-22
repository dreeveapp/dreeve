<?php

namespace App\Tests\Application\Build\BuildGearMaintenanceHtml;

use App\Application\Build\BuildGearMaintenanceHtml\BuildGearMaintenanceHtml;
use App\Application\Build\BuildGearMaintenanceHtml\BuildGearMaintenanceHtmlCommandHandler;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Application\BuildAppFilesTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideGearMaintenanceConfig;
use Twig\Environment;

class BuildGearMaintenanceHtmlCommandHandlerTest extends BuildAppFilesTestCase
{
    use ProvideGearMaintenanceConfig;

    public function testHandle(): void
    {
        $this->importGearMaintenanceConfig();

        $gear = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('g10130856'))
            ->build();
        $this->getContainer()->get(GearRepository::class)->add($gear);

        $this->getContainer()->get(
            GearRepository::class)->add(GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('retired'))
            ->withIsRetired(true)
            ->build()
            );

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withName(Name::fromString('#sfs-chain-lubed'))
                ->withGearId($gear->getId())
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('10'))
                ->withName(Name::fromString('#sfs-chain-lubed'))
                ->withGearId(GearId::fromUnprefixed('retired'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId($gear->getId())
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 01:00:00'))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withGearId($gear->getId())
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 02:00:00'))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withName(Name::fromString('#sfs-chain-lubed wrong'))
                ->withGearId(GearId::random())
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->build(),
            []
        ));

        // Maintenance history is now persisted (it used to be derived from the hashtags
        // in the activity titles above). Persist the rows those tags represent.
        $gearMaintenanceLogRepository = $this->getContainer()->get(GearMaintenanceLogRepository::class);
        $gearMaintenanceLogRepository->add(GearMaintenanceLog::create(
            gearId: $gear->getId(),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        ));
        $gearMaintenanceLogRepository->add(GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('retired'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        ));

        $this->commandBus->dispatch(new BuildGearMaintenanceHtml());
        $this->assertFileSystemWrites($this->getContainer()->get('build_html.storage'));
    }

    public function testHandleWhenDisabled(): void
    {
        $fileStorage = $this->getContainer()->get('build_html.storage');

        new BuildGearMaintenanceHtmlCommandHandler(
            gearMaintenanceRepository: $this->getContainer()->get(GearMaintenanceRepository::class),
            gearRepository: $this->getContainer()->get(GearRepository::class),
            maintenanceTaskProgressCalculator: $this->getContainer()->get(MaintenanceTaskProgressCalculator::class),
            twig: $this->getContainer()->get(Environment::class),
            buildHtmlStorage: $fileStorage,
        )->handle(
            new BuildGearMaintenanceHtml()
        );
        $this->assertFileSystemWrites($fileStorage);
    }
}
