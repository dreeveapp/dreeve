<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class DaemonSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/daemon');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheDaemonSettingsForm(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/daemon');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="daemon"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][importDataAndBuildApp][expression]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][importDataAndBuildApp][enabled]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][gearMaintenanceNotification][expression]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][appUpdateAvailableNotification][expression]"]'));
    }

    public function testItRendersTheSettingsNavigationWithDaemonActive(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/daemon');

        $this->assertResponseIsSuccessful();

        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Daemon', $selectedLink->text());
    }
}
