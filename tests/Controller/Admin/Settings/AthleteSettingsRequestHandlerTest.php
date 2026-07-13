<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Tests\Controller\Admin\AdminWebTestCase;

class AthleteSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/athlete');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheAthleteForm(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/athlete');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-athlete-settings"]'));

        $this->assertCount(0, $crawler->filter('input[name="group"]'));
        $this->assertCount(1, $crawler->filter('input[name="athlete[firstName]"]'));
        $this->assertCount(1, $crawler->filter('input[name="athlete[lastName]"]'));
        $this->assertCount(1, $crawler->filter('input[name="athlete[birthday]"]'));
        $this->assertCount(1, $crawler->filter('select[name="athlete[gender]"]'));
        $this->assertCount(1, $crawler->filter('select[name="athlete[maxHeartRateFormula]"]'));
        $this->assertCount(1, $crawler->filter('select[name="athlete[restingHeartRateFormula]"]'));
        $this->assertCount(1, $crawler->filter('input[name="athlete[restingHeartRateFormulaFixedValue]"]'));

        $this->assertCount(1, $crawler->filter('select[name="athlete[maxHeartRateFormula]"] option[value="fox"][selected]'));
        $this->assertCount(1, $crawler->filter('select[name="athlete[restingHeartRateFormula]"] option[value="heuristicAgeBased"][selected]'));

        // Every other admin page is off limits until the athlete has been configured.
        $this->assertCount(0, $crawler->filter('#drawer-navigation'));
        $this->assertCount(0, $crawler->filter('nav.contextual-panel'));
        $this->assertCount(0, $crawler->filter('[data-drawer-toggle="drawer-navigation"]'));
        $this->assertStringNotContainsString('Return to app', $crawler->filter('body')->text());
    }

    public function testItRendersDateRangeBasedFormulasAsRows(): void
    {
        /** @var SettingsRepository $settingsRepository */
        $settingsRepository = $this->getContainer()->get(KeyValueBasedSettingsRepository::class);
        $settings = $settingsRepository->find(SettingsGroup::GENERAL);
        $settings['athlete']['maxHeartRateFormula'] = ['2023-01-01' => 180];
        $settings['athlete']['restingHeartRateFormula'] = ['2023-01-01' => 58];
        $settingsRepository->save(SettingsGroup::GENERAL, $settings);

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/athlete');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('select[name="athlete[maxHeartRateFormula]"] option[value="dateRangeBased"][selected]'));
        $this->assertCount(1, $crawler->filter('select[name="athlete[restingHeartRateFormula]"] option[value="dateRangeBased"][selected]'));

        $rows = $crawler->filter('[data-repeater-list]')->extract(['data-repeater-initial']);
        $this->assertSame([
            '[{"on":"2023-01-01","bpm":180}]',
            '[{"on":"2023-01-01","bpm":58}]',
        ], $rows);
    }
}
