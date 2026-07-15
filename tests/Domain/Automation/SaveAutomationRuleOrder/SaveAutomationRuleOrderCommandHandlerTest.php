<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\SaveAutomationRuleOrder;

use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\DbalAutomationRuleRepository;
use App\Domain\Automation\SaveAutomationRuleOrder\SaveAutomationRuleOrder;
use App\Domain\Automation\SaveAutomationRuleOrder\SaveAutomationRuleOrderCommandHandler;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Automation\AutomationRuleBuilder;

class SaveAutomationRuleOrderCommandHandlerTest extends ContainerTestCase
{
    private DbalAutomationRuleRepository $repository;
    private SaveAutomationRuleOrderCommandHandler $handler;

    public function testHandleReassignsSortOrderByPosition(): void
    {
        foreach (['a' => 0, 'b' => 1, 'c' => 2] as $id => $sortOrder) {
            $this->repository->add(
                AutomationRuleBuilder::fromDefaults()
                    ->withAutomationRuleId(AutomationRuleId::fromUnprefixed($id))
                    ->withSortOrder($sortOrder)
                    ->build()
            );
        }

        $this->handler->handle(SaveAutomationRuleOrder::fromPayload([
            'order' => ['automationRule-c', 'automationRule-a', 'automationRule-b'],
        ]));

        $this->assertSame(
            ['automationRule-c', 'automationRule-a', 'automationRule-b'],
            $this->repository->findAll()->map(static fn ($rule): string => (string) $rule->getId())
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DbalAutomationRuleRepository($this->getConnection());
        $this->handler = new SaveAutomationRuleOrderCommandHandler($this->repository);
    }
}
