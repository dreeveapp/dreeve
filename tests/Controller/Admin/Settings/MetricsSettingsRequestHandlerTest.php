<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Domain\Activity\Eddington\Config\EddingtonConfiguration;
use App\Tests\Controller\Admin\AdminWebTestCase;

class MetricsSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/metrics');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheMetricsSettingsForm(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/metrics');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="metrics"]'));
        $this->assertCount(1, $crawler->filter('template[data-repeater-template] input[name="data[eddington][__index__][label]"]'));
        $this->assertCount(1, $crawler->filter('template[data-repeater-template] input[name="data[excludeActivitiesFromPeakPowerOutputs][]"]'));
    }

    public function testItSurfacesTheDefaultEddingtonConfigurationWhenNothingIsStored(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/metrics');

        $this->assertResponseIsSuccessful();

        $initial = $crawler->filter('div[data-repeater]')->first()->filter('[data-repeater-list]')->attr('data-repeater-initial');
        $decoded = json_decode((string) $initial, true);

        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
        $this->assertSame(
            EddingtonConfiguration::getDefaultConfig(),
            $decoded,
        );
    }

    public function testItRendersTheSettingsNavigationWithMetricsActive(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/metrics');

        $this->assertResponseIsSuccessful();

        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Metrics', $selectedLink->text());
    }
}
