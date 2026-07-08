<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Domain\Import\ImportMode;
use App\Tests\Controller\Admin\AdminWebTestCase;

class ImportSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/import');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheImportSettingsForm(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/import');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="import"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[numberOfNewActivitiesToProcessPerImport]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[webhooks][verifyToken]"]'));
    }

    public function testItRendersTheSettingsNavigationWithImportActive(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/import');

        $this->assertResponseIsSuccessful();

        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Strava import', $selectedLink->text());
    }

    public function testItReturnsNotFoundInFileImportMode(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings/import');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItShowsTheStravaImportNavItemInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseIsSuccessful();

        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(
            1,
            $settingsPanel->filter('a[href$="/admin/settings/import"]'),
        );
    }

    public function testItHidesTheStravaImportNavItemInFileImportMode(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseIsSuccessful();

        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(
            0,
            $settingsPanel->filter('a[href$="/admin/settings/import"]'),
        );
    }
}
