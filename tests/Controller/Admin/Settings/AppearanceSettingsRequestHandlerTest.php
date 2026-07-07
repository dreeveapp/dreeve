<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class AppearanceSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/appearance');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheAppearanceSettingsForm(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/appearance');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="appearance"]'));
        $this->assertCount(1, $crawler->filter('select[name="data[unitSystem]"]'));
        $this->assertCount(1, $crawler->filter('select[name="data[locale]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[dateFormat][short]"]'));
    }

    public function testItRendersTheSettingsNavigationWithAppearanceActive(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/appearance');

        $this->assertResponseIsSuccessful();

        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Appearance', $selectedLink->text());
    }
}
