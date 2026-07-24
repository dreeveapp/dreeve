<?php

namespace App\Tests\Controller\Admin\Settings\Automation;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
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
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Automation\AutomationRuleBuilder;

class TestAutomationRulesRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/automation-rules/test');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItReturnsANotFoundWhenNotInFileImportMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        static::getContainer()->get(AutomationRuleRepository::class)->add(
            AutomationRuleBuilder::fromDefaults()->build()
        );

        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings/automation-rules/test');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItReturnsANotFoundResponseWhenThereAreNoAutomationRules(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings/automation-rules/test');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRendersTheFormWithoutAnActivityId(): void
    {
        $this->withImportMode(ImportMode::FILES);
        static::getContainer()->get(AutomationRuleRepository::class)->add(
            AutomationRuleBuilder::fromDefaults()->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules/test');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Test automation rules', $crawler->filter('h3')->text());
        $this->assertCount(1, $crawler->filter('input[name="activityId"][data-autocomplete-url]'));
    }

    public function testItRendersTheTraceForAValidActivityId(): void
    {
        $this->withImportMode(ImportMode::FILES);
        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::RIDE)
                ->build(),
            [],
        ));
        static::getContainer()->get(AutomationRuleRepository::class)->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
                ->withLabel('Name my rides')
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::SPORT_TYPE, RuleConfiguration::fromConfig(['operator' => 'isOneOf', 'sportTypes' => ['Ride']])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Automated ride name'])),
                ]))
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules/test?activityId=1');

        $this->assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        $this->assertStringContainsString('Name my rides', $body);
        $this->assertStringContainsString('A rule applies', $body);
        $this->assertStringContainsString('Applied', $body);
        $this->assertStringContainsString('Stops here', $body);
        $this->assertStringContainsString('Automated ride name', $body);

        $matchedConditionPills = $crawler->filter('.rounded-full.pill--success')->reduce(
            fn ($node): bool => str_contains($node->text(), 'is one of Ride'),
        );
        $this->assertCount(1, $matchedConditionPills);
        $this->assertStringContainsString('Sport type', $matchedConditionPills->text());
    }

    public function testItRendersANotFoundErrorForAnUnknownActivityId(): void
    {
        $this->withImportMode(ImportMode::FILES);
        static::getContainer()->get(AutomationRuleRepository::class)->add(
            AutomationRuleBuilder::fromDefaults()->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules/test?activityId=does-not-exist');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No activity found for that ID.', $crawler->filter('body')->text());
    }
}
