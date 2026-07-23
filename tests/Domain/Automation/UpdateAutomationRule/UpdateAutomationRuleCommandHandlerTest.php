<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\UpdateAutomationRule;

use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\AutomationRuleComponents;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\DbalAutomationRuleRepository;
use App\Domain\Automation\UpdateAutomationRule\UpdateAutomationRule;
use App\Domain\Automation\UpdateAutomationRule\UpdateAutomationRuleCommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Automation\AutomationRuleBuilder;
use App\Tests\Domain\Automation\Fixture\DeviceCondition;
use App\Tests\Domain\Automation\Fixture\SetNameAction;

class UpdateAutomationRuleCommandHandlerTest extends ContainerTestCase
{
    private DbalAutomationRuleRepository $repository;
    private UpdateAutomationRuleCommandHandler $handler;

    public function testHandle(): void
    {
        $this->repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
                ->withLabel('Before')
                ->withIsEnabled(true)
                ->withSortOrder(3)
                ->build()
        );

        $this->handler->handle(UpdateAutomationRule::fromPayload([
            'automationRuleId' => 'automationRule-1',
            'label' => 'After',
            'enabled' => false,
            'stopProcessing' => false,
            'conditions' => [['type' => 'device', 'config' => ['deviceName' => 'Garmin']]],
            'actions' => [['type' => 'setName', 'config' => ['name' => 'Commute']]],
        ]));

        $rule = $this->repository->find(AutomationRuleId::fromUnprefixed('1'));
        $this->assertSame('After', $rule->getLabel());
        $this->assertFalse($rule->isEnabled());
        $this->assertFalse($rule->stopProcessing());
        // sortOrder is preserved by the update.
        $this->assertSame(3, $rule->getSortOrder());
        $this->assertSame('[{"type":"device","config":{"deviceName":"Garmin"}}]', Json::encode($rule->getConditions()));
        $this->assertSame('[{"type":"setName","config":{"name":"Commute"}}]', Json::encode($rule->getActions()));
    }

    public function testThrowsWhenRuleDoesNotExist(): void
    {
        $this->expectExceptionObject(new EntityNotFound('AutomationRule "automationRule-1" not found'));

        $this->handler->handle(UpdateAutomationRule::fromPayload([
            'automationRuleId' => 'automationRule-1',
            'label' => 'After',
            'conditions' => [['type' => 'device', 'config' => ['deviceName' => 'Garmin']]],
            'actions' => [['type' => 'setName', 'config' => ['name' => 'Commute']]],
        ]));
    }

    public function testThrowsWhenConfigurationIsInvalid(): void
    {
        $this->repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
                ->build()
        );

        $this->expectExceptionObject(CouldNotProcessCommand::withReason('A "deviceName" is required.'));

        $this->handler->handle(UpdateAutomationRule::fromPayload([
            'automationRuleId' => 'automationRule-1',
            'label' => 'After',
            'conditions' => [['type' => 'device', 'config' => ['deviceName' => '']]],
            'actions' => [['type' => 'setName', 'config' => ['name' => 'Commute']]],
        ]));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DbalAutomationRuleRepository($this->getConnection());
        $this->handler = new UpdateAutomationRuleCommandHandler(
            new AutomationRuleComponents(
                new Conditions([new DeviceCondition()]),
                new Actions([new SetNameAction()]),
            ),
            $this->repository,
        );
    }
}
