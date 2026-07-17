<?php

declare(strict_types=1);

namespace App\Tests\Application\Import\FileImport\ImportActivityFiles\Pipeline;

use App\Application\Import\FileImport\ImportActivityFiles\Pipeline\ActivityImportContext;
use App\Application\Import\FileImport\ImportActivityFiles\Pipeline\ApplyAutomationRules;
use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleEngine;
use App\Domain\Automation\AutomationRuleMatcher;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\DbalAutomationRuleRepository;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\String\Path;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Automation\AutomationRuleBuilder;
use App\Tests\Domain\Automation\Fixture\DistanceCondition;
use App\Tests\Domain\Automation\Fixture\SetNameAction;

class ApplyAutomationRulesTest extends ContainerTestCase
{
    private DbalAutomationRuleRepository $repository;
    private ApplyAutomationRules $applyAutomationRules;

    public function testProcessAppliesMatchingRules(): void
    {
        $this->repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['minKm' => 0])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Automated'])),
                ]))
                ->build()
        );

        $context = ActivityImportContext::create(Path::fromString('/tmp/activity.fit'))
            ->withActivity(ActivityBuilder::fromDefaults()->build());

        $context = $this->applyAutomationRules->process($context);

        $this->assertSame('Automated', $context->getActivity()->getName());
    }

    public function testProcessThrowsWhenNoActivityOnContext(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Activity not set on $context'));

        $this->applyAutomationRules->process(
            ActivityImportContext::create(Path::fromString('/tmp/activity.fit'))
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DbalAutomationRuleRepository($this->getConnection());
        $this->applyAutomationRules = new ApplyAutomationRules(
            new AutomationRuleEngine(
                $this->repository,
                new AutomationRuleMatcher(new Conditions([new DistanceCondition()])),
                new Actions([new SetNameAction()]),
            ),
        );
    }
}
