<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class SecuritySettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/security');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheSecuritySettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/security');

        $this->assertResponseIsSuccessful();

        // The security settings form.
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="security"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[requiresAuthentication]"]'));

        // The environment driven credentials, shown for reference only.
        $this->assertEquals(self::ADMIN_USERNAME, $crawler->filter('input#adminUsername[disabled]')->attr('value'));
        $this->assertEquals('', $crawler->filter('input#adminAllowedIpAddresses[disabled]')->attr('value'));
        $this->assertStringContainsString(
            'Your current IP address, as seen by Dreeve, is 127.0.0.1.',
            $crawler->filter('input#adminAllowedIpAddresses[disabled] + p.description')->text()
        );

        // The settings navigation, with "Security" active.
        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Security', $selectedLink->text());
    }
}
