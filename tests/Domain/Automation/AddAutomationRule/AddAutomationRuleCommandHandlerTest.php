<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\AddAutomationRule;

use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\AddAutomationRule\AddAutomationRule;
use App\Domain\Automation\AddAutomationRule\AddAutomationRuleCommandHandler;
use App\Domain\Automation\AutomationRuleComponents;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\DbalAutomationRuleRepository;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Automation\AutomationRuleBuilder;
use App\Tests\Domain\Automation\Fixture\DeviceCondition;
use App\Tests\Domain\Automation\Fixture\SetNameAction;

class AddAutomationRuleCommandHandlerTest extends ContainerTestCase
{
    private DbalAutomationRuleRepository $repository;
    private AddAutomationRuleCommandHandler $handler;

    public function testHandle(): void
    {
        $this->handler->handle(AddAutomationRule::fromPayload([
            'label' => 'Tag commutes',
            'enabled' => false,
            'conditions' => [['type' => 'device', 'config' => ['deviceName' => 'Garmin']]],
            'actions' => [['type' => 'setName', 'config' => ['name' => 'Commute']]],
        ]));

        $rules = $this->repository->findAll();
        $this->assertCount(1, $rules);

        $rule = $rules->getFirst();
        $this->assertSame('Tag commutes', $rule->getLabel());
        $this->assertFalse($rule->isEnabled());
        $this->assertSame(0, $rule->getSortOrder());
        $this->assertSame('[{"type":"device","config":{"deviceName":"Garmin"}}]', Json::encode($rule->getConditions()));
        $this->assertSame('[{"type":"setName","config":{"name":"Commute"}}]', Json::encode($rule->getActions()));
        $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:15:04'), $rule->getCreatedOn());
    }

    public function testSortOrderIsAppended(): void
    {
        $this->repository->add(AutomationRuleBuilder::fromDefaults()->build());

        $this->handler->handle(AddAutomationRule::fromPayload([
            'label' => 'Second',
            'conditions' => [['type' => 'device', 'config' => ['deviceName' => 'Garmin']]],
            'actions' => [['type' => 'setName', 'config' => ['name' => 'Commute']]],
        ]));

        $appended = $this->repository->findAll()->getLast();
        $this->assertSame('Second', $appended->getLabel());
        $this->assertSame(1, $appended->getSortOrder());
    }

    public function testThrowsWhenConfigurationIsInvalid(): void
    {
        $this->expectExceptionObject(CouldNotProcessCommand::withReason('A "deviceName" is required.'));

        $this->handler->handle(AddAutomationRule::fromPayload([
            'label' => 'Broken',
            'conditions' => [['type' => 'device', 'config' => ['deviceName' => '']]],
            'actions' => [['type' => 'setName', 'config' => ['name' => 'Commute']]],
        ]));
    }

    public function testThrowsWhenTypeIsNotRegistered(): void
    {
        $this->expectExceptionObject(CouldNotProcessCommand::withReason('No condition registered for type "sportType".'));

        $this->handler->handle(AddAutomationRule::fromPayload([
            'label' => 'Unregistered',
            'conditions' => [['type' => 'sportType', 'config' => []]],
            'actions' => [['type' => 'setName', 'config' => ['name' => 'Commute']]],
        ]));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DbalAutomationRuleRepository($this->getConnection());
        $this->handler = new AddAutomationRuleCommandHandler(
            new AutomationRuleComponents(
                new Conditions([new DeviceCondition()]),
                new Actions([new SetNameAction()]),
            ),
            $this->repository,
            $this->getContainer()->get(Clock::class),
        );
    }
}
