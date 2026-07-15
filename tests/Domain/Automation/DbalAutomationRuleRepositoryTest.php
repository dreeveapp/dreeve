<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\DbalAutomationRuleRepository;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use Spatie\Snapshots\MatchesSnapshots;

class DbalAutomationRuleRepositoryTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private DbalAutomationRuleRepository $repository;

    public function testAddAndFind(): void
    {
        $rule = AutomationRuleBuilder::fromDefaults()
            ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
            ->withLabel('Tag commutes')
            ->withConditions(ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['deviceName' => 'Garmin'])),
            ]))
            ->withActions(ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Commute'])),
            ]))
            ->build();
        $this->repository->add($rule);

        $this->assertEquals($rule, $this->repository->find($rule->getId()));
    }

    public function testAddPersistsColumns(): void
    {
        $this->repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['minKm' => 10])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::fromConfig(['isCommute' => true])),
                ]))
                ->build()
        );

        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM AutomationRule')->fetchAllAssociative()
        );
    }

    public function testFindThrowsWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('AutomationRule "automationRule-1" not found'));

        $this->repository->find(AutomationRuleId::fromUnprefixed('1'));
    }

    public function testFindAllOrdersBySortOrder(): void
    {
        $this->repository->add($this->ruleWithSortOrder('a', 2));
        $this->repository->add($this->ruleWithSortOrder('b', 0));
        $this->repository->add($this->ruleWithSortOrder('c', 1));

        $this->assertSame(
            ['automationRule-b', 'automationRule-c', 'automationRule-a'],
            $this->repository->findAll()->map(static fn ($rule): string => (string) $rule->getId())
        );
    }

    public function testUpdate(): void
    {
        $rule = AutomationRuleBuilder::fromDefaults()
            ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
            ->withLabel('Before')
            ->withIsEnabled(true)
            ->build();
        $this->repository->add($rule);

        $this->repository->update(
            $rule
                ->withLabel('After')
                ->withIsEnabled(false)
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['minKm' => 5])),
                ]))
        );

        $updated = $this->repository->find($rule->getId());
        $this->assertSame('After', $updated->getLabel());
        $this->assertFalse($updated->isEnabled());
        $this->assertSame(
            '[{"type":"distance","config":{"minKm":5}}]',
            Json::encode($updated->getConditions())
        );
    }

    public function testUpdateOrder(): void
    {
        $this->repository->add($this->ruleWithSortOrder('a', 0));
        $this->repository->add($this->ruleWithSortOrder('b', 1));
        $this->repository->add($this->ruleWithSortOrder('c', 2));

        $this->repository->updateOrder([
            AutomationRuleId::fromUnprefixed('c'),
            AutomationRuleId::fromUnprefixed('a'),
            AutomationRuleId::fromUnprefixed('b'),
        ]);

        $this->assertSame(
            ['automationRule-c', 'automationRule-a', 'automationRule-b'],
            $this->repository->findAll()->map(static fn ($rule): string => (string) $rule->getId())
        );
    }

    public function testDelete(): void
    {
        $rule = $this->ruleWithSortOrder('1', 0);
        $this->repository->add($rule);

        $this->repository->delete($rule->getId());

        $this->assertCount(0, $this->repository->findAll());
    }

    public function testHydrateSkipsUnknownComponentTypes(): void
    {
        $this->getConnection()->insert('AutomationRule', [
            'automationRuleId' => 'automationRule-1',
            'label' => 'Stale rule',
            'isEnabled' => 1,
            'sortOrder' => 0,
            'conditions' => Json::encode([
                ['type' => 'someRemovedCondition', 'config' => []],
                ['type' => 'distance', 'config' => ['minKm' => 3]],
            ]),
            'actions' => Json::encode([
                ['type' => 'someRemovedAction', 'config' => []],
            ]),
            'createdOn' => '2023-10-17 16:15:04',
        ]);

        $rule = $this->repository->find(AutomationRuleId::fromUnprefixed('1'));

        $this->assertSame(
            '[{"type":"distance","config":{"minKm":3}}]',
            Json::encode($rule->getConditions())
        );
        $this->assertTrue($rule->getActions()->isEmpty());
    }

    private function ruleWithSortOrder(string $id, int $sortOrder): \App\Domain\Automation\AutomationRule
    {
        return AutomationRuleBuilder::fromDefaults()
            ->withAutomationRuleId(AutomationRuleId::fromUnprefixed($id))
            ->withSortOrder($sortOrder)
            ->build();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DbalAutomationRuleRepository($this->getConnection());
    }
}
