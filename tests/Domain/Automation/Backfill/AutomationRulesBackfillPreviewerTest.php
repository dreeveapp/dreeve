<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Backfill;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleEngine;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRules;
use App\Domain\Automation\Backfill\AutomationRulesBackfillPreviewer;
use App\Domain\Automation\Backfill\MatchedActivity;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\DbalAutomationRuleRepository;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Automation\AutomationRuleBuilder;

class AutomationRulesBackfillPreviewerTest extends ContainerTestCase
{
    private ActivityRepository $activityRepository;
    private DbalAutomationRuleRepository $automationRuleRepository;
    private AutomationRulesBackfillPreviewer $previewer;

    public function testListsEveryMatchingActivity(): void
    {
        $this->addRide('matches', isCommute: false);
        $this->addRide('matches-without-changing-anything', isCommute: true);
        $this->addRun('no-match');

        $preview = $this->previewer->preview($this->automationRuleRepository->findAll()->enabled());

        $this->assertSame(3, $preview->getTotalScanned());
        $this->assertEqualsCanonicalizing(
            ['activity-matches', 'activity-matches-without-changing-anything'],
            array_map(static fn (MatchedActivity $matched): string => (string) $matched->getActivity()->getId(), $preview->getMatchedActivities())
        );
        $this->assertSame(
            ['automationRule-1'],
            $preview->getMatchedActivities()[0]->getAppliedAutomationRuleIds()->map(strval(...)),
            'Every matched activity carries the rules that would run on it.'
        );

        $withoutRules = $this->previewer->preview(AutomationRules::empty());
        $this->assertSame(3, $withoutRules->getTotalScanned());
        $this->assertCount(0, $withoutRules->getMatchedActivities(), 'Only the rules handed in are applied.');
    }

    private function addRun(string $id): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withSportType(SportType::RUN)
                ->build(),
            [],
        ));
    }

    private function addRide(string $id, bool $isCommute): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withSportType(SportType::RIDE)
                ->withIsCommute($isCommute)
                ->build(),
            [],
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->automationRuleRepository = new DbalAutomationRuleRepository($this->getConnection());
        $this->automationRuleRepository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::fromConfig(['isCommute' => true])),
                ]))
                ->build()
        );
        $this->previewer = new AutomationRulesBackfillPreviewer(
            $this->activityRepository,
            $this->getContainer()->get(AutomationRuleEngine::class),
        );
    }
}
