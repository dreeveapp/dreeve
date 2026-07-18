<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorkoutType;
use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\Action\SetNameAction;
use App\Domain\Automation\AutomationRuleEngine;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleMatcher;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\DbalAutomationRuleRepository;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\GearId;
use App\Domain\Gear\RecordingDevice\RecordingDeviceId;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class AutomationRuleEngineTest extends ContainerTestCase
{
    private AutomationRuleEngine $engine;
    private DbalAutomationRuleRepository $repository;

    public function testAppliesEveryActionOfTheMatchedRule(): void
    {
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::fromConfig(['isCommute' => true])),
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Morning commute'])),
                new ConfiguredAction(ActionType::SET_DESCRIPTION, RuleConfiguration::fromConfig(['description' => 'Auto-tagged'])),
                new ConfiguredAction(ActionType::SET_WORKOUT_TYPE, RuleConfiguration::fromConfig(['workoutType' => 'race'])),
            ]),
        );

        $result = $this->engine->apply(
            ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build()
        );

        $this->assertTrue($result->isCommute());
        $this->assertSame('Morning commute', $result->getName());
        $this->assertSame('Auto-tagged', $result->getDescription());
        $this->assertSame(WorkoutType::RACE, $result->getWorkoutType());
    }

    public function testOnlyTheFirstMatchingRuleIsApplied(): void
    {
        $this->saveRule(
            id: 'first',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'First'])),
            ]),
        );
        $this->saveRule(
            id: 'second',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Second'])),
                new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::fromConfig(['isCommute' => true])),
            ]),
            sortOrder: 1,
        );

        $result = $this->engine->apply(
            ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->withIsCommute(false)->build()
        );

        $this->assertSame('First', $result->getName());
        $this->assertFalse($result->isCommute(), 'The second rule must never run once the first matched.');
    }

    public function testDisabledRulesAreSkipped(): void
    {
        $this->saveRule(
            id: 'disabled',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'From disabled rule'])),
            ]),
            enabled: false,
        );
        $this->saveRule(
            id: 'enabled',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'From enabled rule'])),
            ]),
            sortOrder: 1,
        );

        $result = $this->engine->apply(
            ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->build()
        );

        $this->assertSame('From enabled rule', $result->getName());
    }

    public function testRuleAppliesOnlyWhenAllConditionsMatch(): void
    {
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
                new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 50.0])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Long ride'])),
            ]),
        );

        $shortRide = ActivityBuilder::fromDefaults()
            ->withName('Untouched')
            ->withSportType(SportType::RIDE)
            ->withDistance(Kilometer::from(10.0))
            ->build();
        $longRide = ActivityBuilder::fromDefaults()
            ->withSportType(SportType::RIDE)
            ->withDistance(Kilometer::from(80.0))
            ->build();

        $this->assertSame('Untouched', $this->engine->apply($shortRide)->getName(), 'One condition failing means the rule does not apply.');
        $this->assertSame('Long ride', $this->engine->apply($longRide)->getName());
    }

    public function testActivityIsUnchangedWhenNoRuleMatches(): void
    {
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Run']])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Should not apply'])),
            ]),
        );

        $ride = ActivityBuilder::fromDefaults()->withName('Original')->withSportType(SportType::RIDE)->build();
        $result = $this->engine->apply($ride);

        $this->assertEquals($ride, $result);
        $this->assertSame('Original', $result->getName());
    }

    public function testMatchesOnPolylineProximityAndAssignsGearAndSportType(): void
    {
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::PASSES_NEAR, RuleConfiguration::fromConfig(['operator' => 'within', 'latitude' => 51.05, 'longitude' => 4.0, 'radius' => 1.0])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::ASSIGN_GEAR, RuleConfiguration::fromConfig(['gearId' => 'gear-my-bike'])),
                new ConfiguredAction(ActionType::SET_SPORT_TYPE, RuleConfiguration::fromConfig(['sportType' => 'GravelRide'])),
            ]),
        );

        $activity = ActivityBuilder::fromDefaults()
            ->withSportType(SportType::RIDE)
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [51.055, 4.0], [45.0, 1.0]]))
            ->build();
        $result = $this->engine->apply($activity);

        $this->assertEquals(GearId::fromString('gear-my-bike'), $result->getGearId());
        $this->assertSame(SportType::GRAVEL_RIDE, $result->getSportType());
    }

    public function testProximityWithinAndOutsideOperators(): void
    {
        $this->saveRule(
            id: 'near',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::STARTS_NEAR, RuleConfiguration::fromConfig(['operator' => 'within', 'latitude' => 51.05, 'longitude' => 4.0, 'radius' => 1.0])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Started near home'])),
            ]),
        );
        $this->saveRule(
            id: 'away',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::STARTS_NEAR, RuleConfiguration::fromConfig(['operator' => 'outside', 'latitude' => 51.05, 'longitude' => 4.0, 'radius' => 1.0])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Started away'])),
            ]),
            sortOrder: 1,
        );

        $startsNear = ActivityBuilder::fromDefaults()->withStartingCoordinate(Coordinate::createFromLatAndLng(
            Latitude::fromString((string) 51.055),
            Longitude::fromString((string) 4.0),
        ))->build();
        $startsFar = ActivityBuilder::fromDefaults()->withStartingCoordinate(Coordinate::createFromLatAndLng(
            Latitude::fromString((string) 51.10),
            Longitude::fromString((string) 4.0),
        ))->build();

        $this->assertSame('Started near home', $this->engine->apply($startsNear)->getName());
        $this->assertSame('Started away', $this->engine->apply($startsFar)->getName());
    }

    public function testMatchesOnWeekdayAndTimeOfDay(): void
    {
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::WEEKDAY, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'weekdays' => [2, 3]])),
                new ConfiguredCondition(ConditionType::TIME_OF_DAY, RuleConfiguration::fromConfig(['operator' => 'lt', 'time' => '09:00'])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Early weekday ride'])),
            ]),
        );

        $tuesdayMorning = ActivityBuilder::fromDefaults()
            ->withName('Untouched')
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 07:30:00'))
            ->build();
        $tuesdayAfternoon = ActivityBuilder::fromDefaults()
            ->withName('Untouched')
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 15:30:00'))
            ->build();

        $this->assertSame('Early weekday ride', $this->engine->apply($tuesdayMorning)->getName());
        $this->assertSame('Untouched', $this->engine->apply($tuesdayAfternoon)->getName(), 'Afternoon fails the time-of-day condition.');
    }

    public function testMatchesOnDeviceAndNegativeSportTypeOperator(): void
    {
        $deviceId = RecordingDeviceId::fromName('Garmin Edge 130')->toUnprefixedString();

        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['operator' => 'is', 'deviceId' => $deviceId])),
                new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isNoneOf', 'sportTypes' => ['Run', 'Walk']])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::fromConfig(['isCommute' => true])),
            ]),
        );

        $matching = ActivityBuilder::fromDefaults()
            ->withSportType(SportType::RIDE)
            ->withDeviceName('Garmin Edge 130')
            ->withIsCommute(false)
            ->build();
        $wrongDevice = ActivityBuilder::fromDefaults()
            ->withSportType(SportType::RIDE)
            ->withDeviceName('Wahoo Elemnt')
            ->withIsCommute(false)
            ->build();

        $this->assertTrue($this->engine->apply($matching)->isCommute());
        $this->assertFalse($this->engine->apply($wrongDevice)->isCommute(), 'A different device must not satisfy the device condition.');
    }

    public function testActivityIsReturnedUnchangedWhenNoRulesExist(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withName('Untouched')->build();

        $this->assertEquals($activity, $this->engine->apply($activity));
    }

    public function testRuleWithoutConditionsIsSkipped(): void
    {
        // A rule that matches nothing on purpose must never apply to every activity.
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::empty(),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Should not apply'])),
            ]),
        );

        $result = $this->engine->apply(ActivityBuilder::fromDefaults()->withName('Untouched')->build());

        $this->assertSame('Untouched', $result->getName());
    }

    public function testStaleStoredComponentTypesAreToleratedAndKnownComponentsStillApply(): void
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
                ['type' => 'someRemovedAction', 'config' => []],
                ['type' => 'setName', 'config' => ['name' => 'Applied']],
            ]),
            'createdOn' => '2023-10-17 16:15:04',
        ]);

        $result = $this->engine->apply(
            ActivityBuilder::fromDefaults()->withName('Untouched')->withSportType(SportType::RIDE)->build()
        );

        $this->assertSame('Applied', $result->getName());
    }

    public function testActionsWithoutARegisteredServiceAreSkipped(): void
    {
        $this->saveRule(
            id: '1',
            conditions: ConfiguredConditions::fromArray([
                new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
            ]),
            actions: ConfiguredActions::fromArray([
                new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::fromConfig(['isCommute' => true])),
                new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Applied'])),
            ]),
        );

        $engine = new AutomationRuleEngine(
            $this->repository,
            $this->getContainer()->get(AutomationRuleMatcher::class),
            new Actions([new SetNameAction()]),
        );

        $result = $engine->apply(
            ActivityBuilder::fromDefaults()->withSportType(SportType::RIDE)->withIsCommute(false)->build()
        );

        $this->assertSame('Applied', $result->getName());
        $this->assertFalse($result->isCommute());
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

        $this->engine = $this->getContainer()->get(AutomationRuleEngine::class);
        $this->repository = new DbalAutomationRuleRepository($this->getConnection());
    }
}
