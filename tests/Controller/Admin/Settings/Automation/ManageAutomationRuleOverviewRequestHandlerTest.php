<?php

namespace App\Tests\Controller\Admin\Settings\Automation;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\RecordingDevice\RecordingDeviceId;
use App\Domain\Import\ImportMode;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Automation\AutomationRuleBuilder;
use App\Tests\Domain\Gear\GearBuilder;

class ManageAutomationRuleOverviewRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/automation-rules');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testRendersTheGatedPanelWhenNotInFileImportMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules');

        $this->assertResponseIsSuccessful();
        $gatedPanel = $crawler->filter('[role="alert"][type="gated-panel"]');
        $this->assertCount(1, $gatedPanel);
        $this->assertStringContainsString(
            'Automation rules are only available in file import mode',
            $gatedPanel->text()
        );
    }

    public function testItRendersTheEmptyState(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('[role="alert"][type="gated-panel"]'));
        $this->assertStringContainsString('No automation rules yet.', $crawler->filter('body')->text());
        $this->assertCount(0, $crawler->filter('[data-sortable-list]'));
        $this->assertCount(0, $crawler->filter('a[href*="automation-rules/test"]'));
    }

    public function testItRendersTheRulesWithTheirConditionsActionsAndState(): void
    {
        $this->withImportMode(ImportMode::FILES);

        $repository = static::getContainer()->get(AutomationRuleRepository::class);
        $repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
                ->withLabel('Tag commutes')
                ->withIsEnabled(true)
                ->withStopProcessing(false)
                ->withSortOrder(0)
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['operator' => 'is', 'deviceId' => 'garmin-edge-530'])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::fromConfig(['isCommute' => true])),
                ]))
                ->build()
        );
        $repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('2'))
                ->withLabel('Assign the gravel bike')
                ->withIsEnabled(false)
                ->withSortOrder(1)
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 50.0])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::ASSIGN_GEAR, RuleConfiguration::fromConfig(['gearId' => 'gear-1'])),
                ]))
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules');

        $this->assertResponseIsSuccessful();

        $this->assertCount(1, $crawler->filter('a[href*="automation-rules/test"]'));

        $list = $crawler->filter('[data-sortable-list]');
        $this->assertCount(1, $list);
        $this->assertSame('save-automation-rule-order', $list->attr('data-save-order-command'));

        $items = $crawler->filter('[data-sort-item]');
        $this->assertCount(2, $items);
        $this->assertSame('automationRule-1', $items->eq(0)->attr('data-sort-id'));
        $this->assertSame('automationRule-2', $items->eq(1)->attr('data-sort-id'));

        $body = $crawler->filter('body')->text();
        $this->assertStringContainsString('Tag commutes', $body);
        $this->assertStringContainsString('Assign the gravel bike', $body);

        // Enabled/disabled badges.
        $this->assertStringContainsString('Enabled', $items->eq(0)->text());
        $this->assertStringContainsString('Disabled', $items->eq(1)->text());

        // Every rule shows what happens after it matches: stop (the default) or continue.
        $this->assertStringContainsString('Continues to next rules', $items->eq(0)->text());
        $this->assertStringNotContainsString('Stops after match', $items->eq(0)->text());
        $this->assertStringContainsString('Stops after match', $items->eq(1)->text());
        $this->assertStringNotContainsString('Continues to next rules', $items->eq(1)->text());

        // Condition/action labels come from the translatable components.
        $this->assertStringContainsString('Recording device', $items->eq(0)->text());
        $this->assertStringContainsString('is garmin-edge-530', $items->eq(0)->text());
        $this->assertStringContainsString('Mark as commute', $items->eq(0)->text());
        $this->assertStringContainsString('Distance', $items->eq(1)->text());
        $this->assertStringContainsString('at least 50 km', $items->eq(1)->text());
        $this->assertStringContainsString('Assign gear', $items->eq(1)->text());
        $this->assertStringContainsString('gear-1', $items->eq(1)->text());

        $distancePill = $items->eq(1)->filter('.rounded-full.pill--neutral')->eq(0);
        $this->assertCount(2, $distancePill->children());
    }

    public function testItRendersAPillForEveryConditionAndActionType(): void
    {
        $this->withImportMode(ImportMode::FILES);

        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()->withName('Canyon Ultimate')->build()
        );
        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('with-device'))
                ->withDeviceName('Garmin Edge 530')
                ->build(),
            [],
        ));

        $repository = static::getContainer()->get(AutomationRuleRepository::class);
        $repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
                ->withSortOrder(0)
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride', 'Run']])),
                    new ConfiguredCondition(ConditionType::WEEKDAY, RuleConfiguration::fromConfig(['operator' => 'isNoneOf', 'weekdays' => [1, 6]])),
                    new ConfiguredCondition(ConditionType::TIME_OF_DAY, RuleConfiguration::fromConfig(['operator' => 'lt', 'time' => '09:30'])),
                    new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['operator' => 'is', 'deviceId' => RecordingDeviceId::fromName('Garmin Edge 530')->toUnprefixedString()])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::ASSIGN_GEAR, RuleConfiguration::fromConfig(['gearId' => 'gear-1'])),
                    new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Automated ride name'])),
                    new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::empty()),
                ]))
                ->build()
        );
        $repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('2'))
                ->withSortOrder(1)
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::DISTANCE, RuleConfiguration::fromConfig(['operator' => 'gte', 'value' => 10.0])),
                    new ConfiguredCondition(ConditionType::STARTS_NEAR, RuleConfiguration::fromConfig(['operator' => 'within', 'latitude' => 51.2, 'longitude' => 3.1, 'radius' => 500.0])),
                    new ConfiguredCondition(ConditionType::ENDS_NEAR, RuleConfiguration::fromConfig(['operator' => 'outside', 'latitude' => 51.2, 'longitude' => 3.1, 'radius' => 500.0])),
                    new ConfiguredCondition(ConditionType::PASSES_NEAR, RuleConfiguration::fromConfig(['operator' => 'within', 'latitude' => 51.2, 'longitude' => 3.1, 'radius' => 250.0])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::SET_SPORT_TYPE, RuleConfiguration::fromConfig(['sportType' => 'Ride'])),
                    new ConfiguredAction(ActionType::SET_WORKOUT_TYPE, RuleConfiguration::fromConfig(['workoutType' => 'race'])),
                    new ConfiguredAction(ActionType::SET_DESCRIPTION, RuleConfiguration::fromConfig(['description' => 'Added by automation'])),
                ]))
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules');

        $this->assertResponseIsSuccessful();

        $items = $crawler->filter('[data-sort-item]');
        $this->assertCount(2, $items);

        $firstRule = $items->eq(0)->text();
        $this->assertStringContainsString('Sport type', $firstRule);
        $this->assertStringContainsString('is one of Rides, Runs', $firstRule);
        $this->assertStringContainsString('Weekday', $firstRule);
        $this->assertStringContainsString('is none of Monday, Saturday', $firstRule);
        $this->assertStringContainsString('Time of day', $firstRule);
        $this->assertStringContainsString('before 09:30', $firstRule);
        $this->assertStringContainsString('Recording device', $firstRule);
        $this->assertStringContainsString('is Garmin Edge 530', $firstRule);
        $this->assertStringContainsString('Assign gear', $firstRule);
        $this->assertStringContainsString('Canyon Ultimate', $firstRule);
        $this->assertStringContainsString('Set name', $firstRule);
        $this->assertStringContainsString('Automated ride name', $firstRule);
        $this->assertStringContainsString('Mark as commute', $firstRule);

        $secondRule = $items->eq(1)->text();
        $this->assertStringContainsString('Distance', $secondRule);
        $this->assertStringContainsString('at least 10 km', $secondRule);
        $this->assertStringContainsString('Starts near', $secondRule);
        $this->assertStringContainsString('within radius 500 m (51.2, 3.1)', $secondRule);
        $this->assertStringContainsString('Ends near', $secondRule);
        $this->assertStringContainsString('outside radius 500 m (51.2, 3.1)', $secondRule);
        $this->assertStringContainsString('Passes near', $secondRule);
        $this->assertStringContainsString('within radius 250 m (51.2, 3.1)', $secondRule);
        $this->assertStringContainsString('Set sport type', $secondRule);
        $this->assertStringContainsString('Rides', $secondRule);
        $this->assertStringContainsString('Set workout type', $secondRule);
        $this->assertStringContainsString('Race', $secondRule);
        $this->assertStringContainsString('Set description', $secondRule);
        $this->assertStringContainsString('Added by automation', $secondRule);

        $markAsCommutePill = $items->eq(0)->filter('.rounded-full.pill--neutral')->reduce(
            fn ($node): bool => str_contains($node->text(), 'Mark as commute'),
        );
        $this->assertCount(1, $markAsCommutePill);
        $this->assertCount(1, $markAsCommutePill->children());
    }
}
