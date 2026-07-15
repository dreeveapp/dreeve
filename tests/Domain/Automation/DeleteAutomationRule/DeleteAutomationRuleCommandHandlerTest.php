<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\DeleteAutomationRule;

use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\DbalAutomationRuleRepository;
use App\Domain\Automation\DeleteAutomationRule\DeleteAutomationRule;
use App\Domain\Automation\DeleteAutomationRule\DeleteAutomationRuleCommandHandler;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Automation\AutomationRuleBuilder;

class DeleteAutomationRuleCommandHandlerTest extends ContainerTestCase
{
    private DbalAutomationRuleRepository $repository;
    private DeleteAutomationRuleCommandHandler $handler;

    public function testHandle(): void
    {
        $this->repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
                ->build()
        );
        $this->repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('2'))
                ->withSortOrder(1)
                ->build()
        );

        $this->handler->handle(DeleteAutomationRule::fromPayload([
            'automationRuleId' => 'automationRule-1',
        ]));

        $rules = $this->repository->findAll();
        $this->assertCount(1, $rules);
        $this->assertSame('automationRule-2', (string) $rules->getFirst()->getId());
    }

    public function testDeletingAnUnknownRuleIsANoOp(): void
    {
        $this->handler->handle(DeleteAutomationRule::fromPayload([
            'automationRuleId' => 'automationRule-does-not-exist',
        ]));

        $this->assertCount(0, $this->repository->findAll());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DbalAutomationRuleRepository($this->getConnection());
        $this->handler = new DeleteAutomationRuleCommandHandler($this->repository);
    }
}
