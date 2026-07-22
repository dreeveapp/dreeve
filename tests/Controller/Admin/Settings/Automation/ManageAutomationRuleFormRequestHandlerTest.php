<?php

namespace App\Tests\Controller\Admin\Settings\Automation;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Import\ImportMode;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Automation\AutomationRuleBuilder;

class ManageAutomationRuleFormRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPageOnAdd(): void
    {
        $this->client->request('GET', '/admin/settings/automation-rules/add');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnEdit(): void
    {
        $this->client->request('GET', '/admin/settings/automation-rules/'.AutomationRuleId::fromUnprefixed('1').'/edit');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnDelete(): void
    {
        $this->client->request('GET', '/admin/settings/automation-rules/'.AutomationRuleId::fromUnprefixed('1').'/delete');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItReturnsANotFoundWhenNotInFileImportModeOnAdd(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings/automation-rules/add');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItReturnsANotFoundWhenNotInFileImportModeOnEdit(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings/automation-rules/'.AutomationRuleId::fromUnprefixed('1').'/edit');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItReturnsANotFoundWhenNotInFileImportModeOnDelete(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings/automation-rules/'.AutomationRuleId::fromUnprefixed('1').'/delete');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRendersTheAddForm(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules/add');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Add automation rule', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="add-automation-rule"]');
        $this->assertCount(1, $form);

        // Add mode carries no id.
        $this->assertCount(0, $form->filter('input[type="hidden"][name="automationRuleId"]'));

        // Both repeaters start empty and offer every registered condition/action type.
        $this->assertStringContainsString('[]', (string) $crawler->filter('[data-repeater-list]')->eq(0)->attr('data-repeater-initial'));
        $conditionOptions = $crawler->filter('select[name="conditions[__index__][type]"] option')->extract(['value']);
        $this->assertContains('device', $conditionOptions);
        $this->assertContains('passesNear', $conditionOptions);
        $actionOptions = $crawler->filter('select[name="actions[__index__][type]"] option')->extract(['value']);
        $this->assertContains('assignGear', $actionOptions);
        $this->assertContains('setDescription', $actionOptions);
    }

    public function testItRendersTheEditFormPrefilledWithTheRule(): void
    {
        $this->withImportMode(ImportMode::FILES);

        static::getContainer()->get(AutomationRuleRepository::class)->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('42'))
                ->withLabel('Tag commutes')
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['operator' => 'is', 'deviceId' => 'garmin-edge-530'])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::MARK_AS_COMMUTE, RuleConfiguration::fromConfig(['isCommute' => true])),
                ]))
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules/'.AutomationRuleId::fromUnprefixed('42').'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Edit automation rule', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="update-automation-rule"]');
        $this->assertCount(1, $form);
        $this->assertSame('automationRule-42', $form->filter('input[type="hidden"][name="automationRuleId"]')->attr('value'));
        $this->assertSame('Tag commutes', $form->filter('input[name="label"]')->attr('value'));

        // The repeaters are seeded with the stored conditions/actions as JSON.
        $conditionsInitial = (string) $crawler->filter('[data-repeater-list]')->eq(0)->attr('data-repeater-initial');
        $this->assertStringContainsString('"type":"device"', $conditionsInitial);
        $this->assertStringContainsString('garmin-edge-530', $conditionsInitial);
        $actionsInitial = (string) $crawler->filter('[data-repeater-list]')->eq(1)->attr('data-repeater-initial');
        $this->assertStringContainsString('"type":"markAsCommute"', $actionsInitial);
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

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules/'.AutomationRuleId::fromUnprefixed('7').'/delete');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Delete automation rule', $crawler->filter('h3')->text());
        $this->assertStringContainsString('Tag commutes', $crawler->filter('body')->text());

        $form = $crawler->filter('form[data-dispatch-command="delete-automation-rule"]');
        $this->assertCount(1, $form);
        $this->assertSame('automationRule-7', $form->filter('input[type="hidden"][name="automationRuleId"]')->attr('value'));
    }
}
