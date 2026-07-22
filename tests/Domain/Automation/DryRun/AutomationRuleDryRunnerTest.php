<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\DryRun;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\DbalAutomationRuleRepository;
use App\Domain\Automation\DryRun\AutomationRuleDryRunner;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\RecordingDevice\RecordingDeviceId;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Automation\AutomationRuleBuilder;

class AutomationRuleDryRunnerTest extends ContainerTestCase
{
    private AutomationRuleDryRunner $dryRunner;
    private DbalAutomationRuleRepository $repository;

    public function testEveryConditionIsReportedWithoutShortCircuiting(): void
    {
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
                new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 50.0])),
            ]),
            actions: $this->setName('Long ride'),
        );

        // A short ride: the sport type matches but the distance does not.
        $dryRun = $this->dryRunner->run(
            ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->withDistance(Kilometer::from(10.0))->build()
        );

        $result = $dryRun->getRuleResults()[0];
        $conditions = $result->getConditionResults();

        $this->assertCount(2, $conditions, 'Both conditions are reported, even though the first would let the engine short-circuit.');
        $this->assertSame(ConditionType::SPORT_TYPE, $conditions[0]->getType());
        $this->assertTrue($conditions[0]->isMatched());
        $this->assertSame(ConditionType::DISTANCE, $conditions[1]->getType());
        $this->assertFalse($conditions[1]->isMatched());

        $this->assertFalse($result->allConditionsMatched());
        $this->assertFalse($dryRun->hasWinner());
    }

    public function testTheFirstEnabledMatchingRuleWinsAndLaterRulesAreNotEvaluated(): void
    {
        $this->saveRule(id: 'first', conditions: $this->sportTypeIsOneOf('Ride'), actions: $this->setName('First'));
        $this->saveRule(id: 'second', conditions: $this->sportTypeIsOneOf('Ride'), actions: $this->setName('Second'), sortOrder: 1);

        $dryRun = $this->dryRunner->run(ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build());

        $this->assertTrue($dryRun->hasWinner());
        $this->assertSame((string) AutomationRuleId::fromUnprefixed('first'), (string) $dryRun->getWinningRuleId());

        [$first, $second] = $dryRun->getRuleResults();

        $this->assertTrue($first->isWinner());
        $this->assertTrue($first->wasEvaluated());
        $this->assertTrue($first->allConditionsMatched());

        $this->assertFalse($second->isWinner());
        $this->assertFalse($second->wasEvaluated(), 'Rules after the winner would never run for this activity.');
    }

    public function testADisabledRuleNeverWinsEvenWhenItsConditionsMatch(): void
    {
        $this->saveRule(id: 'disabled', conditions: $this->sportTypeIsOneOf('Ride'), actions: $this->setName('From disabled rule'), enabled: false);
        $this->saveRule(id: 'enabled', conditions: $this->sportTypeIsOneOf('Ride'), actions: $this->setName('From enabled rule'), sortOrder: 1);

        $dryRun = $this->dryRunner->run(ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build());

        [$disabled, $enabled] = $dryRun->getRuleResults();

        $this->assertTrue($disabled->allConditionsMatched(), 'Its conditions still match…');
        $this->assertFalse($disabled->isWinner(), '…but a disabled rule never wins.');
        $this->assertTrue($disabled->wasEvaluated());
        $this->assertTrue($enabled->isWinner());
        $this->assertSame((string) AutomationRuleId::fromUnprefixed('enabled'), (string) $dryRun->getWinningRuleId());
    }

    public function testThereIsNoWinnerWhenNoRuleMatches(): void
    {
        $this->saveRule(id: '1', conditions: $this->sportTypeIsOneOf('Run'), actions: $this->setName('Should not apply'));

        $dryRun = $this->dryRunner->run(ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build());

        $this->assertFalse($dryRun->hasWinner());
        $this->assertNull($dryRun->getWinningRuleId());

        $result = $dryRun->getRuleResults()[0];
        $this->assertFalse($result->isWinner());
        $this->assertFalse($result->allConditionsMatched());
        $this->assertTrue($result->wasEvaluated(), 'Without a winner, every rule is evaluated.');
    }

    public function testARuleWithoutConditionsNeverMatches(): void
    {
        $this->saveRule(id: '1', conditions: ConfiguredConditions::empty(), actions: $this->setName('Should not apply'));

        $dryRun = $this->dryRunner->run(ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build());

        $result = $dryRun->getRuleResults()[0];
        $this->assertEmpty($result->getConditionResults());
        $this->assertFalse($result->allConditionsMatched());
        $this->assertFalse($result->isWinner());
        $this->assertFalse($dryRun->hasWinner());
    }

    public function testAllConditionsMustMatchForARuleToMatch(): void
    {
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
                new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 50.0])),
            ]),
            actions: $this->setName('Long ride'),
        );

        $longRide = ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->withDistance(Kilometer::from(80.0))->build();
        $shortRide = ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->withDistance(Kilometer::from(10.0))->build();

        $this->assertTrue($this->dryRunner->run($longRide)->hasWinner());
        $this->assertFalse($this->dryRunner->run($shortRide)->hasWinner(), 'One failing condition means the rule does not match.');
    }

    public function testMatchesOnDeviceCondition(): void
    {
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig([
                    'operator' => 'is',
                    'deviceId' => RecordingDeviceId::fromName('Garmin Edge 130')->toUnprefixedString(),
                ])),
            ]),
            actions: $this->setName('Recorded on the Garmin'),
        );

        $matching = ActivityBuilder::fromDefaults()->withDeviceName('Garmin Edge 130')->build();
        $other = ActivityBuilder::fromDefaults()->withDeviceName('Wahoo Elemnt')->build();

        $this->assertTrue($this->dryRunner->run($matching)->getRuleResults()[0]->isWinner());
        $this->assertFalse($this->dryRunner->run($other)->hasWinner());
    }

    public function testMatchesOnWeekdayAndTimeOfDay(): void
    {
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::WEEKDAY, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'weekdays' => [2, 3]])),
                new ConfiguredCondition(ConditionType::TIME_OF_DAY, RuleConfiguration::fromConfig(['operator' => 'lt', 'time' => '09:00'])),
            ]),
            actions: $this->setName('Early weekday ride'),
        );

        $tuesdayMorning = ActivityBuilder::fromDefaults()->withStartDateTime(SerializableDateTime::fromString('2023-10-10 07:30:00'))->build();
        $tuesdayAfternoon = ActivityBuilder::fromDefaults()->withStartDateTime(SerializableDateTime::fromString('2023-10-10 15:30:00'))->build();

        $this->assertTrue($this->dryRunner->run($tuesdayMorning)->hasWinner());

        $afternoon = $this->dryRunner->run($tuesdayAfternoon)->getRuleResults()[0];
        $this->assertFalse($afternoon->allConditionsMatched());
        $this->assertTrue($afternoon->getConditionResults()[0]->isMatched(), 'Weekday matches…');
        $this->assertFalse($afternoon->getConditionResults()[1]->isMatched(), '…but the time of day does not.');
    }

    public function testMatchesOnStartsNearProximity(): void
    {
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::STARTS_NEAR, RuleConfiguration::fromConfig(['operator' => 'within', 'latitude' => 51.05, 'longitude' => 4.0, 'radius' => 1000.0])),
            ]),
            actions: $this->setName('Started near home'),
        );

        $near = ActivityBuilder::fromDefaults()->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('51.055'), Longitude::fromString('4.0')))->build();
        $far = ActivityBuilder::fromDefaults()->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('51.10'), Longitude::fromString('4.0')))->build();

        $this->assertTrue($this->dryRunner->run($near)->hasWinner());
        $this->assertFalse($this->dryRunner->run($far)->hasWinner());
    }

    public function testTheWinnersConfiguredActionsAreExposed(): void
    {
        $this->saveRule(
            id: '1',
            conditions: $this->sportTypeIsOneOf('Ride'),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Morning commute'])),
                new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::empty()),
            ]),
        );

        $actions = iterator_to_array(
            $this->dryRunner->run(ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build())
                ->getRuleResults()[0]->getConfiguredActions()
        );

        $this->assertCount(2, $actions);
        $this->assertSame(ActionType::SET_NAME, $actions[0]->getType());
        $this->assertSame(ActionType::MARK_AS_COMMUTE, $actions[1]->getType());
    }

    public function testConfiguredActionsAreExposedForEveryRuleNotJustTheWinner(): void
    {
        $this->saveRule(id: 'winner', conditions: $this->sportTypeIsOneOf('Ride'), actions: $this->setName('Winner'));
        $this->saveRule(id: 'loser', conditions: $this->sportTypeIsOneOf('Run'), actions: $this->setName('Loser'), sortOrder: 1);

        $dryRun = $this->dryRunner->run(ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build());

        [$winner, $loser] = $dryRun->getRuleResults();
        $this->assertCount(1, $winner->getConfiguredActions());
        $this->assertFalse($loser->isWinner());
        $this->assertCount(1, $loser->getConfiguredActions(), 'Configured actions are available for non-winning rules too.');
    }

    public function testUnregisteredStoredConditionTypesAreIgnoredWhileKnownOnesStillMatch(): void
    {
        $this->getConnection()->insert('AutomationRule', [
            'automationRuleId' => 'automationRule-1',
            'label' => 'Raw rule',
            'isEnabled' => 1,
            'sortOrder' => 0,
            'conditions' => Json::encode([
                ['type' => 'someRemovedCondition', 'config' => []],
                ['type' => 'sportType', 'config' => ['operator' => 'isOneOf', 'sportTypes' => ['Ride']]],
            ]),
            'actions' => Json::encode([
                ['type' => 'setName', 'config' => ['name' => 'Applied']],
            ]),
            'createdOn' => '2023-10-17 16:15:04',
        ]);

        $dryRun = $this->dryRunner->run(ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build());

        $result = $dryRun->getRuleResults()[0];
        $this->assertCount(1, $result->getConditionResults(), 'The unregistered condition type is ignored.');
        $this->assertSame(ConditionType::SPORT_TYPE, $result->getConditionResults()[0]->getType());
        $this->assertTrue($result->isWinner());
    }

    private function sportTypeIsOneOf(string ...$sportTypes): ConfiguredConditions
    {
        return ConfiguredConditions::fromArray([
            new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => $sportTypes])),
        ]);
    }

    private function setName(string $name): ConfiguredActions
    {
        return ConfiguredActions::fromArray([
            new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => $name])),
        ]);
    }

    private function saveRule(
        string $id,
        ConfiguredConditions $conditions,
        ConfiguredActions $actions,
        int $sortOrder = 0,
        bool $enabled = true,
    ): void {
        $this->repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed($id))
                ->withSortOrder($sortOrder)
                ->withIsEnabled($enabled)
                ->withConditions($conditions)
                ->withActions($actions)
                ->build()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->dryRunner = $this->getContainer()->get(AutomationRuleDryRunner::class);
        $this->repository = new DbalAutomationRuleRepository($this->getConnection());
    }
}
