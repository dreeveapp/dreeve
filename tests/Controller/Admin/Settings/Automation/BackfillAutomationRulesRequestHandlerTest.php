<?php

namespace App\Tests\Controller\Admin\Settings\Automation;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleIds;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\Backfill\AutomationRulesBackfillQueue;
use App\Domain\Automation\Backfill\AutomationRulesBackfillRequest;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\GearRepository;
use App\Domain\Import\ImportMode;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Automation\AutomationRuleBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class BackfillAutomationRulesRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/automation-rules/backfill');

        $this->assertResponseRedirects('/admin/login');
    }

    #[DataProvider('provideNotFoundScenarios')]
    public function testItIsNotFound(ImportMode $importMode, bool $ruleIsEnabled, bool $backfillIsQueued, string $url): void
    {
        $this->withImportMode($importMode);
        $this->saveAssignGearRule(isEnabled: $ruleIsEnabled);
        if ($backfillIsQueued) {
            new AutomationRulesBackfillQueue(static::getContainer()->get(KeyValueStore::class))->queue(
                AutomationRulesBackfillRequest::fromState(
                    AutomationRuleIds::fromArray([AutomationRuleId::fromUnprefixed('1')]),
                    ActivityIds::fromArray([ActivityId::fromUnprefixed('changes')]),
                )
            );
        }
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', $url);

        $this->assertResponseStatusCodeSame(404);
    }

    public static function provideNotFoundScenarios(): iterable
    {
        $page = '/admin/settings/automation-rules/backfill';
        $preview = $page.'/preview';

        yield 'strava import mode' => [ImportMode::STRAVA_API, true, false, $page];
        yield 'no enabled rules' => [ImportMode::FILES, false, false, $page];
        yield 'preview in strava import mode' => [ImportMode::STRAVA_API, true, false, $preview];
        yield 'preview while a backfill is queued' => [ImportMode::FILES, true, true, $preview.'?automationRuleIds[]=automationRule-1'];
        yield 'preview without a selection' => [ImportMode::FILES, true, false, $preview];
        yield 'preview of a rule that does not exist' => [ImportMode::FILES, true, false, $preview.'?automationRuleIds[]=automationRule-other'];
    }

    public function testThePageDefersScanningUntilRulesAreSelected(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->saveAssignGearRule();
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules/backfill');

        $this->assertResponseIsSuccessful();

        $this->assertCount(1, $crawler->filter('.tabs a[href*="automation-rules/test"]'));
        $this->assertCount(1, $crawler->filter('.tabs a[href*="automation-rules/backfill"]'));

        $ruleCheckboxes = $crawler->filter('form[method="GET"] input[name="automationRuleIds[]"]');
        $this->assertCount(1, $ruleCheckboxes);
        $this->assertSame('automationRule-1', $ruleCheckboxes->attr('value'));
        $this->assertNull($ruleCheckboxes->attr('checked'), 'Nothing is selected until the user picks a rule.');
        $this->assertCount(0, $crawler->filter('[data-async-content-url]'));
        $this->assertStringContainsString('No rules selected yet.', $crawler->filter('body')->text());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules/backfill?automationRuleIds[]=automationRule-1');

        $this->assertResponseIsSuccessful();
        $this->assertNotNull($crawler->filter('form[method="GET"] input[name="automationRuleIds[]"]')->attr('checked'));

        $placeholder = $crawler->filter('[data-async-content-url]');
        $this->assertCount(1, $placeholder);
        $this->assertStringStartsWith('/admin/settings/automation-rules/backfill/preview', (string) $placeholder->attr('data-async-content-url'));
        $this->assertCount(1, $placeholder->filter('.loader'));
        $this->assertNull($placeholder->attr('class'));

        $this->assertStringNotContainsString('Activities that would change', $crawler->filter('body')->text());
        $this->assertCount(0, $crawler->filter('form[data-dispatch-command]'));
    }

    public function testItRendersThePreview(): void
    {
        $this->withImportMode(ImportMode::FILES);

        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()->withName('Canyon Ultimate')->build()
        );
        $this->saveAssignGearRule();

        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('changes'))
                ->withName('Morning ride')
                ->withSportType(SportType::RIDE)
                ->build(),
            [],
        ));
        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('no-match'))
                ->withName('Evening run')
                ->withSportType(SportType::RUN)
                ->build(),
            [],
        ));

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules/backfill/preview?automationRuleIds[]=automationRule-1');

        $this->assertResponseIsSuccessful();

        $body = $crawler->text();
        $this->assertStringContainsString('Matching activities', $body);
        $this->assertStringNotContainsString('Matches per rule', $body);

        $this->assertStringContainsString('Morning ride', $body);
        $this->assertSame('Assign the gravel bike', $crawler->filter('label .pill')->text());
        $this->assertStringNotContainsString('Evening run', $body);
        $this->assertStringNotContainsString('Test activity', $body);

        $form = $crawler->filter('form[data-dispatch-command]');
        $this->assertCount(1, $form);
        $this->assertSame('queue-automation-rules-backfill', $form->attr('data-dispatch-command'));

        $this->assertSame('automationRule-1', $form->filter('input[type="hidden"][name="automationRuleIds[]"]')->attr('value'));
        $activityCheckboxes = $form->filter('input[type="checkbox"][name="activityIds[]"]');
        $this->assertCount(1, $activityCheckboxes);
        $this->assertSame('activity-changes', $activityCheckboxes->attr('value'));
        $this->assertNotNull($activityCheckboxes->attr('checked'));
    }

    public function testItRendersTheQueuedStateWithoutAPreview(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->saveAssignGearRule();
        new AutomationRulesBackfillQueue(static::getContainer()->get(KeyValueStore::class))->queue(
            AutomationRulesBackfillRequest::fromState(
                AutomationRuleIds::fromArray([AutomationRuleId::fromUnprefixed('1')]),
                ActivityIds::fromArray([ActivityId::fromUnprefixed('changes')]),
            )
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/automation-rules/backfill');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('[data-async-content-url]'));
        $this->assertStringContainsString(
            'A backfill is already queued.',
            $crawler->filter('body')->text()
        );
    }

    private function saveAssignGearRule(bool $isEnabled = true): void
    {
        static::getContainer()->get(AutomationRuleRepository::class)->add(
            AutomationRuleBuilder::fromDefaults()
                ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('1'))
                ->withLabel('Assign the gravel bike')
                ->withIsEnabled($isEnabled)
                ->withConditions(ConfiguredConditions::fromArray([
                    new ConfiguredCondition(ConditionType::NAME, RuleConfiguration::fromConfig(['operator' => 'contains', 'value' => 'Morning'])),
                ]))
                ->withActions(ConfiguredActions::fromArray([
                    new ConfiguredAction(ActionType::ASSIGN_GEAR, RuleConfiguration::fromConfig(['gearId' => 'gear-1'])),
                ]))
                ->build()
        );
    }
}
