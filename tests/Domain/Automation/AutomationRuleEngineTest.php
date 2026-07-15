<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation;

use App\Domain\Activity\Activity;
use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleEngine;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\DbalAutomationRuleRepository;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Automation\Fixture\DeviceCondition;
use App\Tests\Domain\Automation\Fixture\DistanceCondition;
use App\Tests\Domain\Automation\Fixture\SetNameAction;

class AutomationRuleEngineTest extends ContainerTestCase
{
    private DbalAutomationRuleRepository $repository;
    private AutomationRuleEngine $engine;

    public function testFirstMatchingRuleWins(): void
    {
        $this->persistRule('1', 0, true, $this->matchAll(), $this->setName('First'));
        $this->persistRule('2', 1, true, $this->matchAll(), $this->setName('Second'));

        $this->assertSame('First', $this->engine->apply($this->activity())->getName());
    }

    public function testDisabledRulesAreSkipped(): void
    {
        $this->persistRule('1', 0, false, $this->matchAll(), $this->setName('Changed'));

        $this->assertSame('Test activity', $this->engine->apply($this->activity())->getName());
    }

    public function testRuleWithoutConditionsIsSkipped(): void
    {
        $this->persistRule('1', 0, true, ConfiguredConditions::empty(), $this->setName('Changed'));

        $this->assertSame('Test activity', $this->engine->apply($this->activity())->getName());
    }

    public function testRuleIsSkippedWhenAConditionTypeIsNotRegistered(): void
    {
        $conditions = ConfiguredConditions::fromArray([
            new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::empty()),
        ]);
        $this->persistRule('1', 0, true, $conditions, $this->setName('Changed'));

        $this->assertSame('Test activity', $this->engine->apply($this->activity())->getName());
    }

    public function testRuleIsSkippedWhenAConditionDoesNotMatch(): void
    {
        $conditions = ConfiguredConditions::fromArray([
            new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['deviceName' => 'Garmin'])),
        ]);

        $this->persistRule('1', 0, true, $conditions, $this->setName('Changed'));

        $this->assertSame('Test activity', $this->engine->apply($this->activity())->getName());
    }

    public function testAllConditionsMustMatch(): void
    {
        $conditions = ConfiguredConditions::fromArray([
            new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['minKm' => 0])),
            new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['deviceName' => 'Wrong'])),
        ]);
        $this->persistRule('1', 0, true, $conditions, $this->setName('Changed'));

        $activity = $this->activity()->withDeviceName('Garmin');
        $this->assertSame('Test activity', $this->engine->apply($activity)->getName());
    }

    public function testUnknownActionTypesAreTolerated(): void
    {
        $actions = ConfiguredActions::fromArray([
            new ConfiguredAction(ActionType::ASSIGN_GEAR, RuleConfiguration::fromConfig(['gearId' => 'x'])),
            new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Applied'])),
        ]);
        $this->persistRule('1', 0, true, $this->matchAll(), $actions);

        $this->assertSame('Applied', $this->engine->apply($this->activity())->getName());
    }

    public function testActivityIsReturnedUnchangedWhenNoRulesExist(): void
    {
        $this->assertSame('Test activity', $this->engine->apply($this->activity())->getName());
    }

    private function activity(): Activity
    {
        return ActivityBuilder::fromDefaults()->build();
    }

    private function matchAll(): ConfiguredConditions
    {
        return ConfiguredConditions::fromArray([
            new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['minKm' => 0])),
        ]);
    }

    private function setName(string $name): ConfiguredActions
    {
        return ConfiguredActions::fromArray([
            new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => $name])),
        ]);
    }

    private function persistRule(
        string $id,
        int $sortOrder,
        bool $isEnabled,
        ConfiguredConditions $conditions,
        ConfiguredActions $actions,
    ): void {
        $this->repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed($id))
                ->withSortOrder($sortOrder)
                ->withIsEnabled($isEnabled)
                ->withConditions($conditions)
                ->withActions($actions)
                ->build()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DbalAutomationRuleRepository($this->getConnection());
        $this->engine = new AutomationRuleEngine(
            $this->repository,
            new Conditions([new DeviceCondition(), new DistanceCondition()]),
            new Actions([new SetNameAction()]),
        );
    }
}
