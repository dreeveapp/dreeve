<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class MapsSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/maps');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheMapsSettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/maps');

        $this->assertResponseIsSuccessful();

        // The maps settings form.
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="maps"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[polylineColor]"]'));
        $this->assertCount(1, $crawler->filter('input[type="checkbox"][name="data[enableGreyScale]"]'));

        // The tile layer list, rendered by the repeater component.
        $this->assertCount(1, $crawler->filter('[data-repeater] [data-repeater-list]'));
        $this->assertCount(1, $crawler->filter('[data-repeater-template] input[name="data[tileLayerUrl][]"]'));
        $this->assertCount(1, $crawler->filter('link[rel="stylesheet"][href*="leaflet.min.css"]'));

        // The heatmap viewport, with the center rendered by the coordinate picker component.
        $this->assertCount(1, $crawler->filter('input[name="data[heatmap][initialZoom]"]'));
        $picker = $crawler->filter('[data-coordinate-picker]');
        $this->assertCount(1, $picker);
        $this->assertCount(1, $picker->filter('input[name="data[heatmap][initialCenter][0]"][data-coordinate-picker-field="latitude"]'));
        $this->assertCount(1, $picker->filter('input[name="data[heatmap][initialCenter][1]"][data-coordinate-picker-field="longitude"]'));
        $this->assertCount(1, $picker->filter('[data-coordinate-picker-map]'));

        // The settings navigation, with "Maps" active.
        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Maps', $selectedLink->text());
    }
}
