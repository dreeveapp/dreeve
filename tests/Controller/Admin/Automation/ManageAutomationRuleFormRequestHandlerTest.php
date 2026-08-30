<?php

namespace App\Tests\Controller\Admin\Automation;

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
use App\Domain\Gear\Sensor\ConnectedSensor;
use App\Domain\Gear\Sensor\ConnectedSensors;
use App\Domain\Gear\Sensor\SensorType;
use App\Domain\Import\ImportMode;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Automation\AutomationRuleBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class ManageAutomationRuleFormRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPageOnAdd(): void
    {
        $this->client->request('GET', '/admin/automation-rules/add');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnEdit(): void
    {
        $this->client->request('GET', '/admin/automation-rules/'.AutomationRuleId::fromUnprefixed('1').'/edit');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnDelete(): void
    {
        $this->client->request('GET', '/admin/automation-rules/'.AutomationRuleId::fromUnprefixed('1').'/delete');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItReturnsANotFoundWhenNotInFileImportModeOnAdd(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/automation-rules/add');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItReturnsANotFoundWhenNotInFileImportModeOnEdit(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/automation-rules/'.AutomationRuleId::fromUnprefixed('1').'/edit');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItReturnsANotFoundWhenNotInFileImportModeOnDelete(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/automation-rules/'.AutomationRuleId::fromUnprefixed('1').'/delete');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRendersTheAddForm(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/automation-rules/add');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Add automation rule', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="add-automation-rule"]');
        $this->assertCount(1, $form);

        // Add mode carries no id.
        $this->assertCount(0, $form->filter('input[type="hidden"][name="automationRuleId"]'));

        // New rules stop processing after a match by default.
        $this->assertCount(1, $form->filter('input[type="checkbox"][name="stopProcessing"][checked]'));

        // Both repeaters start empty and offer every registered condition/action type.
        $this->assertStringContainsString('[]', (string) $crawler->filter('[data-repeater-list]')->eq(0)->attr('data-repeater-initial'));
        $conditionOptions = $crawler->filter('select[name="conditions[__index__][type]"] option')->extract(['value']);
        $this->assertContains('name', $conditionOptions);
        $this->assertContains('device', $conditionOptions);
        $this->assertContains('averagePower', $conditionOptions);
        $this->assertContains('averageCadence', $conditionOptions);
        $this->assertContains('elevation', $conditionOptions);
        $this->assertContains('movingTime', $conditionOptions);
        $this->assertContains('passesNear', $conditionOptions);
        $this->assertNotContains('connectedSensors', $conditionOptions);
        $actionOptions = $crawler->filter('select[name="actions[__index__][type]"] option')->extract(['value']);
        $this->assertContains('assignGear', $actionOptions);
        $this->assertContains('setDescription', $actionOptions);

        // The set device action ships a combobox around a plain, submittable text input.
        $this->assertCount(1, $crawler->filter('[data-combobox]'));
        $this->assertCount(1, $crawler->filter('[data-combobox] input[type="text"][name="actions[__index__][config][deviceName]"][data-repeater-field="config.deviceName"][required]'));
        // Without recorded devices there is nothing to pick from.
        $this->assertCount(0, $crawler->filter('[data-combobox-toggle]'));
        $this->assertCount(0, $crawler->filter('[data-combobox-panel]'));

        // Every proximity condition ships a coordinate picker wired to its own lat/lng/radius inputs.
        $this->assertCount(3, $crawler->filter('[data-coordinate-picker]'));
        $this->assertCount(3, $crawler->filter('[data-coordinate-picker-map]'));
        $this->assertCount(3, $crawler->filter('[data-coordinate-picker-field="latitude"]'));
        $this->assertCount(3, $crawler->filter('[data-coordinate-picker-field="longitude"]'));
        $this->assertCount(3, $crawler->filter('[data-coordinate-picker-field="radius"]'));
        $this->assertSame(
            '{"radiusToMeter":1}',
            $crawler->filter('[data-coordinate-picker]')->eq(0)->attr('data-coordinate-picker'),
        );
    }

    public function testItRendersTheRecordedDevicesAsComboboxOptions(): void
    {
        $this->withImportMode(ImportMode::FILES);

        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withDeviceName('Garmin Edge 530')
                ->build(),
            []
        ));

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/automation-rules/add');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('[data-combobox-toggle]'));
        $this->assertSame(
            ['Garmin Edge 530'],
            $crawler->filter('[data-combobox-option]')->extract(['data-combobox-option'])
        );
    }

    public function testItOnlyOffersTheSensorsTheAthleteActuallyOwns(): void
    {
        $this->withImportMode(ImportMode::FILES);

        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withConnectedSensors(ConnectedSensors::fromSensors(
                    ConnectedSensor::create(1, 3592, 3485049140, 'Garmin Varia', SensorType::BIKE_RADAR, SensorType::BIKE_LIGHT),
                ))
                ->build(),
            []
        ));

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/automation-rules/add');

        $this->assertResponseIsSuccessful();
        $this->assertContains(
            'connectedSensors',
            $crawler->filter('select[name="conditions[__index__][type]"] option')->extract(['value'])
        );
        $this->assertSame(
            ['bikeRadar', 'bikeLight'],
            $crawler->filter('input[name="conditions[__index__][config][sensorTypes][]"]')->extract(['value'])
        );
    }

    public function testItRendersTheEditFormPrefilledWithTheRule(): void
    {
        $this->withImportMode(ImportMode::FILES);

        static::getContainer()->get(AutomationRuleRepository::class)->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('42'))
                ->withLabel('Tag commutes')
                ->withStopProcessing(false)
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['operator' => 'is', 'deviceId' => 'garmin-edge-530'])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::fromConfig(['isCommute' => true])),
                ]))
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/automation-rules/'.AutomationRuleId::fromUnprefixed('42').'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Edit automation rule', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="update-automation-rule"]');
        $this->assertCount(1, $form);
        $this->assertSame('automationRule-42', $form->filter('input[type="hidden"][name="automationRuleId"]')->attr('value'));
        $this->assertSame('Tag commutes', $form->filter('input[name="label"]')->attr('value'));
        $this->assertCount(0, $form->filter('input[type="checkbox"][name="stopProcessing"][checked]'));

        // The repeaters are seeded with the stored conditions/actions as JSON.
        $conditionsInitial = (string) $crawler->filter('[data-repeater-list]')->eq(0)->attr('data-repeater-initial');
        $this->assertStringContainsString('"type":"device"', $conditionsInitial);
        $this->assertStringContainsString('garmin-edge-530', $conditionsInitial);
        $actionsInitial = (string) $crawler->filter('[data-repeater-list]')->eq(1)->attr('data-repeater-initial');
        $this->assertStringContainsString('"type":"markAsCommute"', $actionsInitial);
    }

    public function testItRendersTheAddFormPrefilledWithTheRuleToCopy(): void
    {
        $this->withImportMode(ImportMode::FILES);

        static::getContainer()->get(AutomationRuleRepository::class)->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('42'))
                ->withLabel('Tag commutes')
                ->withStopProcessing(false)
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['operator' => 'is', 'deviceId' => 'garmin-edge-530'])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::fromConfig(['isCommute' => true])),
                ]))
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/automation-rules/add?copyFrom='.AutomationRuleId::fromUnprefixed('42'));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Add automation rule', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="add-automation-rule"]');
        $this->assertCount(1, $form);

        // A copy is a new rule, so it carries no id.
        $this->assertCount(0, $form->filter('input[type="hidden"][name="automationRuleId"]'));
        $this->assertSame('Tag commutes (copy)', $form->filter('input[name="label"]')->attr('value'));
        // The copied rule mirrors the source, not the add form defaults.
        $this->assertCount(0, $form->filter('input[type="checkbox"][name="stopProcessing"][checked]'));

        $conditionsInitial = (string) $crawler->filter('[data-repeater-list]')->eq(0)->attr('data-repeater-initial');
        $this->assertStringContainsString('"type":"device"', $conditionsInitial);
        $this->assertStringContainsString('garmin-edge-530', $conditionsInitial);
        $actionsInitial = (string) $crawler->filter('[data-repeater-list]')->eq(1)->attr('data-repeater-initial');
        $this->assertStringContainsString('"type":"markAsCommute"', $actionsInitial);
    }

    #[DataProvider('provideInvalidRuleToCopy')]
    public function testItRendersAnEmptyAddFormWhenTheRuleToCopyCannotBeResolved(string $copyFrom): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/automation-rules/add?copyFrom='.$copyFrom);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Add automation rule', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="add-automation-rule"]');
        $this->assertCount(0, $form->filter('input[type="hidden"][name="automationRuleId"]'));
        $this->assertSame('', $form->filter('input[name="label"]')->attr('value'));
        $this->assertSame('[]', $crawler->filter('[data-repeater-list]')->eq(0)->attr('data-repeater-initial'));
        $this->assertSame('[]', $crawler->filter('[data-repeater-list]')->eq(1)->attr('data-repeater-initial'));
    }

    public static function provideInvalidRuleToCopy(): iterable
    {
        yield 'unknown rule' => ['automationRule-does-not-exist'];
        yield 'empty value' => [''];
    }

    public function testItRendersTheDeleteConfirmation(): void
    {
        $this->withImportMode(ImportMode::FILES);

        static::getContainer()->get(AutomationRuleRepository::class)->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('7'))
                ->withLabel('Tag commutes')
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/automation-rules/'.AutomationRuleId::fromUnprefixed('7').'/delete');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Delete automation rule', $crawler->filter('h3')->text());
        $this->assertStringContainsString('Tag commutes', $crawler->filter('body')->text());

        $form = $crawler->filter('form[data-dispatch-command="delete-automation-rule"]');
        $this->assertCount(1, $form);
        $this->assertSame('automationRule-7', $form->filter('input[type="hidden"][name="automationRuleId"]')->attr('value'));
    }
}
