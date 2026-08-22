<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Domain\Api\Token;
use App\Tests\Controller\Admin\AdminWebTestCase;

class SecuritySettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheApiKeyCardWhenAKeyIsConfigured(): void
    {
        $this->withApiKey((string) Token::generate());
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/security');

        $this->assertSame('Configured', $crawler->filter('input#apiKeyState[disabled]')->attr('value'));
        $this->assertStringNotContainsString('drv_', $crawler->filter('main')->text());
    }

    public function testItWarnsWhenTheConfiguredApiKeyIsMalformed(): void
    {
        // Strict format validation makes this reachable, and it would otherwise be a silent 401.
        $this->withApiKey('replace-me');
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/security');

        $this->assertStringContainsString(
            'DREEVE_API_KEY is set, but it is not a key this app generated',
            $crawler->filter('main')->text(),
        );
    }

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

        // The API access card, with no key configured.
        $this->assertStringContainsString('Not configured, the API is closed', (string) $crawler->filter('input#apiKeyState[disabled]')->attr('value'));
        $this->assertCount(1, $crawler->filter('a[href="/admin/settings/api-key/generate"]'));
        // The key itself must never reach the shared settings render context.
        $this->assertStringNotContainsString('drv_', $crawler->filter('main')->text());

        // The settings navigation, with "Security" active.
        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Security', $selectedLink->text());
    }
}
