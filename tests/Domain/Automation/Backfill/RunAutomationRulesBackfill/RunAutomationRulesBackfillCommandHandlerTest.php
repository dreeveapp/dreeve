<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Backfill\RunAutomationRulesBackfill;

use App\Application\AppUrl;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleEngine;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleIds;
use App\Domain\Automation\Backfill\AutomationRulesBackfillQueue;
use App\Domain\Automation\Backfill\AutomationRulesBackfillRequest;
use App\Domain\Automation\Backfill\RunAutomationRulesBackfill\RunAutomationRulesBackfill;
use App\Domain\Automation\Backfill\RunAutomationRulesBackfill\RunAutomationRulesBackfillCommandHandler;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\DbalAutomationRuleRepository;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Infrastructure\KeyValue\DbalKeyValueStore;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Automation\AutomationRuleBuilder;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class RunAutomationRulesBackfillCommandHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ActivityRepository $activityRepository;
    private DbalAutomationRuleRepository $automationRuleRepository;
    private AutomationRulesBackfillQueue $queue;
    private SpyCommandBus $commandBus;
    private RunAutomationRulesBackfillCommandHandler $handler;

    public function testHandle(): void
    {
        $this->automationRuleRepository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
                ->withStopProcessing(false)
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Commute ride'])),
                    new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::fromConfig(['isCommute' => true])),
                ]))
                ->build()
        );
        $this->automationRuleRepository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('2'))
                ->withSortOrder(1)
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::SET_DESCRIPTION, RuleConfiguration::fromConfig(['description' => 'From the unselected rule'])),
                ]))
                ->build()
        );

        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('changes'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-12'))
                ->withName('Morning ride')
                ->withSportType(SportType::RIDE)
                ->withIsCommute(false)
                ->build(),
            ['device_watts' => true],
        ));
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('no-op'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-11'))
                ->withName('Commute ride')
                ->withSportType(SportType::RIDE)
                ->withIsCommute(true)
                ->build(),
            [],
        ));
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('no-match'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
                ->withName('Evening run')
                ->withSportType(SportType::RUN)
                ->withIsCommute(false)
                ->build(),
            [],
        ));
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('excluded'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-09'))
                ->withName('Excluded ride')
                ->withSportType(SportType::RIDE)
                ->withIsCommute(false)
                ->build(),
            [],
        ));

        $this->queue->queue(AutomationRulesBackfillRequest::fromState(
            AutomationRuleIds::fromArray([AutomationRuleId::fromUnprefixed('1')]),
            ActivityIds::fromArray(array_map(
                ActivityId::fromUnprefixed(...),
                ['changes', 'no-op', 'no-match', 'deleted']
            )),
        ));

        $output = new SpyOutput();
        $this->handler->handle(new RunAutomationRulesBackfill($output));

        $this->assertMatchesTextSnapshot($output);

        $changed = $this->activityRepository->findWithRawData(ActivityId::fromUnprefixed('changes'));
        $this->assertSame('Commute ride', $changed->getActivity()->getName());
        $this->assertTrue($changed->getActivity()->isCommute());
        $this->assertSame(['device_watts' => true], $changed->getRawData(), 'The raw data of an updated activity is preserved.');

        $this->assertSame('Evening run', $this->activityRepository->find(ActivityId::fromUnprefixed('no-match'))->getName());
        $this->assertFalse($this->activityRepository->find(ActivityId::fromUnprefixed('no-match'))->isCommute());

        $excluded = $this->activityRepository->find(ActivityId::fromUnprefixed('excluded'));
        $this->assertSame('Excluded ride', $excluded->getName(), 'An activity left out of the request is never touched.');
        $this->assertFalse($excluded->isCommute());

        $this->assertSame('', $changed->getActivity()->getDescription(), 'A rule that was not selected never runs.');

        $this->assertFalse($this->queue->isQueued(), 'The queued request is cleared once the backfill completed.');

        $dispatchedCommands = $this->commandBus->getDispatchedCommands();
        $this->assertCount(1, $dispatchedCommands);
        $this->assertInstanceOf(SendNotification::class, $dispatchedCommands[0]);
        $this->assertSame('Automation rules applied to 3 activities', $dispatchedCommands[0]->getMessage());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->automationRuleRepository = new DbalAutomationRuleRepository($this->getConnection());
        $this->queue = new AutomationRulesBackfillQueue(new DbalKeyValueStore($this->getConnection()));
        $this->commandBus = new SpyCommandBus();

        $this->handler = new RunAutomationRulesBackfillCommandHandler(
            $this->activityRepository,
            $this->getContainer()->get(AutomationRuleEngine::class),
            $this->automationRuleRepository,
            $this->queue,
            $this->commandBus,
            $this->getContainer()->get(AppUrl::class),
        );
    }
}
