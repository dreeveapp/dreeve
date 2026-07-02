<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class SettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/dashboard');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheDashboardSettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Dashboard', $crawler->filter('main')->text());
    }

    public function testItRendersTheSettingsNavigation(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/dashboard');

        $this->assertResponseIsSuccessful();

        // The Settings item in the icon rail is highlighted.
        $this->assertCount(1, $crawler->filter('#drawer-navigation a[title="Settings"][aria-selected="true"]'));

        // The Settings contextual panel is shown with the (selected) Dashboard link.
        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Dashboard', $selectedLink->text());
    }
}
