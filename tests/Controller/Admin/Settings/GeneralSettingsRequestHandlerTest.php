<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

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
        // Three native repeaters: weight, FTP cycling, FTP running.
        $this->assertCount(3, $crawler->filter('form[data-dispatch-command="update-settings"] [data-repeater]'));

        // The seeded weight history is passed to the first repeater as its list-shaped initial rows.
        $initial = (string) $crawler->filter('[data-repeater-list]')->first()->attr('data-repeater-initial');
        $this->assertStringContainsString('2020-01-01', $initial);
        $this->assertStringContainsString('"weight"', $initial);
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
