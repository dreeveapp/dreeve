<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class IntegrationsSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/integrations');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheIntegrationsSettingsForm(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/integrations');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="integrations"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[ai][enabled]"]'));
        $this->assertCount(1, $crawler->filter('template[data-repeater-template] input[name="data[ai][agent][commands][__index__][command]"]'));
        $this->assertCount(1, $crawler->filter('template[data-repeater-template] input[name="data[notifications][services][]"]'));
    }

    public function testItRendersTheSettingsNavigationWithIntegrationsActive(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/integrations');

        $this->assertResponseIsSuccessful();

        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Integrations', $selectedLink->text());
    }
}
