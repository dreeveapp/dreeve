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
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Automation\AutomationRuleBuilder;

class ManageAutomationRuleOverviewRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/automation-rules');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheEmptyState(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No automation rules yet.', $crawler->filter('body')->text());
        $this->assertCount(0, $crawler->filter('[data-sortable-list]'));
    }

    public function testItRendersTheRulesWithTheirConditionsActionsAndState(): void
    {
        $repository = static::getContainer()->get(AutomationRuleRepository::class);
        $repository->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
                ->withLabel('Tag commutes')
                ->withIsEnabled(true)
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

        $this->assertStringContainsString('Enabled', $items->eq(0)->text());
        $this->assertStringContainsString('Disabled', $items->eq(1)->text());

        $this->assertStringContainsString('Recording device', $items->eq(0)->text());
        $this->assertStringContainsString('Mark as commute', $items->eq(0)->text());
        $this->assertStringContainsString('Distance', $items->eq(1)->text());
        $this->assertStringContainsString('Assign gear', $items->eq(1)->text());
    }
}
