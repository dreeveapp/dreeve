<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class GeneralSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheGeneralSettingsForm(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="general"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[athlete][birthday]"]'));
    }

    public function testItRendersTheWeightAndFtpHistoryEditors(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseIsSuccessful();
        // Five repeaters: max heart rate ranges, resting heart rate ranges, weight, FTP cycling, FTP running.
        $this->assertCount(5, $crawler->filter('form[data-dispatch-command="update-settings"] [data-repeater]'));

        $initial = (string) $crawler->filter('[data-repeater]')
            ->reduce(fn (Crawler $repeater) => str_contains($repeater->html(), 'data[athlete][weightHistory]'))
            ->filter('[data-repeater-list]')
            ->attr('data-repeater-initial');
        $this->assertStringContainsString('2020-01-01', $initial);
        $this->assertStringContainsString('"weight"', $initial);
    }

    public function testItRendersTheHeartRateZonesEditor(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('select[name="data[athlete][heartRateZones][mode]"]'));
        // Five default zone rows, pre-filled since the baseline has no custom zones.
        $this->assertCount(5, $crawler->filter('input[name^="data[athlete][heartRateZones][zones]"][name$="[from]"]'));
        $this->assertCount(1, $crawler->filter('textarea[name="data[athlete][heartRateZones][advanced]"]'));
    }

    public function testItRendersTheSettingsNavigationWithGeneralActive(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseIsSuccessful();

        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('General', $selectedLink->text());
    }
}
