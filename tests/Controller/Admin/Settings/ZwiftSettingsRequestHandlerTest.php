<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class ZwiftSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/zwift');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheZwiftSettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/zwift');

        $this->assertResponseIsSuccessful();

        // The Zwift settings form.
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="zwift"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[level]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[racingScore]"]'));

        // The settings navigation, with "Zwift" active.
        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Zwift', $selectedLink->text());
    }
}
