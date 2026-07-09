<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Domain\Import\ImportMode;
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
        $this->assertCount(1, $crawler->filter('input[name="data[cron][runStravaImportAndBuildApp][expression]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][runStravaImportAndBuildApp][enabled]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][gearMaintenanceNotification][expression]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][appUpdateAvailableNotification][expression]"]'));
        $this->assertStringContainsString(
            'Changes to these settings only take effect after you restart the daemon container.',
            $crawler->filter('form[data-dispatch-command="update-settings"]')->text(),
        );
    }

    public function testItHidesTheStravaImportActionInFileImportMode(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/daemon');

        $this->assertResponseIsSuccessful();
        // The Strava import action is not supported in file import mode.
        $this->assertCount(0, $crawler->filter('input[name="data[cron][runStravaImportAndBuildApp][expression]"]'));
        // The notification actions are supported in every import mode.
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
