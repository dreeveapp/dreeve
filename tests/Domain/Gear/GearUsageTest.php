<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\GearUsage;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Automation\AutomationRuleBuilder;
use App\Tests\ProvideGearMaintenanceConfig;

class GearUsageTest extends ContainerTestCase
{
    use ProvideGearMaintenanceConfig;

    private GearRepository $gearRepository;
    private GearUsage $gearUsage;

    public function testItIsNotInUseWithoutReferences(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('g1233776'))->build());
        $this->getContainer()->get(AutomationRuleRepository::class)->add(
            AutomationRuleBuilder::fromDefaults()
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::ASSIGN_GEAR, RuleConfiguration::fromConfig(['gearId' => 'gear-other'])),
                ]))
                ->build()
        );

        $this->assertFalse($this->gearUsage->isInUse($this->gearRepository->find(GearId::fromUnprefixed('g1233776'))));
    }

    public function testItIsInUseWhenLinkedToAnActivity(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('g1233776'))->build());
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('g1233776'))
                ->build(),
            []
        ));

        $this->assertTrue($this->gearUsage->isInUse($this->gearRepository->find(GearId::fromUnprefixed('g1233776'))));
    }

    public function testItIsInUseWhenAttachedToAMaintenanceComponent(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('g1233776'))->build());
        $this->importGearMaintenanceConfig();

        $this->assertTrue($this->gearUsage->isInUse($this->gearRepository->find(GearId::fromUnprefixed('g1233776'))));
    }

    public function testItIsInUseWhenItHasMaintenanceLogs(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('g1233776'))->build());
        $this->getContainer()->get(GearMaintenanceLogRepository::class)->add(GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g1233776'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        ));

        $this->assertTrue($this->gearUsage->isInUse($this->gearRepository->find(GearId::fromUnprefixed('g1233776'))));
    }

    public function testItIsInUseWhenAnAutomationRuleAssignsIt(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('g1233776'))->build());
        $this->getContainer()->get(AutomationRuleRepository::class)->add(
            AutomationRuleBuilder::fromDefaults()
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::ASSIGN_GEAR, RuleConfiguration::fromConfig(['gearId' => 'gear-g1233776'])),
                ]))
                ->build()
        );

        $this->assertTrue($this->gearUsage->isInUse($this->gearRepository->find(GearId::fromUnprefixed('g1233776'))));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->gearRepository = $this->getContainer()->get(GearRepository::class);
        $this->gearUsage = $this->getContainer()->get(GearUsage::class);
    }
}
