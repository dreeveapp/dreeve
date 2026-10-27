<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Security\Token\ApiToken;
use App\Infrastructure\Security\Token\McpToken;
use App\Tests\Controller\Admin\AdminWebTestCase;

class SecuritySettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheEnvironmentDrivenAccessWhenConfigured(): void
    {
        $this->withApiKey((string) ApiToken::generate());
        $this->withMcpKey((string) McpToken::generate());
        $this->withOidc('https://idp.example.com/application/o/dreeve/', 'dreeve', 'oidc-client-secret', 'athlete-subject, athlete@example.com');
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/security');

        $this->assertSame('Configured', $crawler->filter('input#apiKeyState[disabled]')->attr('value'));
        $this->assertSame('Configured', $crawler->filter('input#mcpKeyState[disabled]')->attr('value'));
        $this->assertStringNotContainsString('drv', $crawler->filter('main')->text());

        $this->assertStringContainsString('Single sign-on (enabled)', $crawler->filter('main')->text());
        $this->assertSame('https://idp.example.com/application/o/dreeve/', $crawler->filter('input#oidcIssuerUrl[disabled]')->attr('value'));
        $this->assertSame('dreeve', $crawler->filter('input#oidcClientId[disabled]')->attr('value'));
        $this->assertSame('Configured', $crawler->filter('input#oidcClientSecretState[disabled]')->attr('value'));
        $this->assertSame('athlete-subject, athlete@example.com', $crawler->filter('input#oidcAllowedUsers[disabled]')->attr('value'));
        $this->assertSame('http://localhost/admin/login/oidc/callback', $crawler->filter('input#oidcRedirectUri[readonly]')->attr('value'));
        $this->assertCount(0, $crawler->filter('a[href="https://docs.dreeve.app/integrations/single-sign-on/"]'));
        $this->assertStringNotContainsString('oidc-client-secret', (string) $this->client->getResponse()->getContent());
    }

    public function testItWarnsWhenTheConfiguredApiKeyIsMalformed(): void
    {
        // Strict format validation makes this reachable, and it would otherwise be a silent 401.
        $this->withApiKey('replace-me');
        $this->withMcpKey('replace-me');
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/security');

        $this->assertStringContainsString(
            'DREEVE_API_KEY is set, but it is not a key this app generated',
            $crawler->filter('main')->text(),
        );
        $this->assertStringContainsString(
            'DREEVE_MCP_KEY is set, but it is not a key this app generated',
            $crawler->filter('main')->text(),
        );
    }

    public function testItChecksTheOriginalFileDownloadOptionWhenVisitorsMayDownload(): void
    {
        static::getContainer()->get(SettingsRepository::class)->saveGroup(SettingsGroup::SECURITY, [
            'visitorsCanDownloadOriginalFiles' => '1',
        ]);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/security');

        $this->assertCount(1, $crawler->filter('input[name="data[visitorsCanDownloadOriginalFiles]"][checked]'));
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
        $this->assertCount(1, $crawler->filter('input[name="data[visitorsCanDownloadOriginalFiles]"]:not([checked])'));

        // The environment driven credentials, shown for reference only.
        $this->assertEquals(self::ADMIN_USERNAME, $crawler->filter('input#adminUsername[disabled]')->attr('value'));
        $this->assertEquals('', $crawler->filter('input#adminAllowedIpAddresses[disabled]')->attr('value'));
        $this->assertStringContainsString(
            'Your current IP address, as seen by Dreeve, is 127.0.0.1.',
            $crawler->filter('input#adminAllowedIpAddresses[disabled] + p.description')->text()
        );

        // The API access card, with no key configured.
        $this->assertStringContainsString('Not configured, API access is disabled', (string) $crawler->filter('input#apiKeyState[disabled]')->attr('value'));
        $this->assertCount(1, $crawler->filter('a[href="/admin/settings/token/generate/api"]'));

        // The MCP access card, with no key configured.
        $this->assertStringContainsString('Not configured, MCP server is disabled', (string) $crawler->filter('input#mcpKeyState[disabled]')->attr('value'));
        $this->assertCount(1, $crawler->filter('a[href="/admin/settings/token/generate/mcp"]'));

        // The single sign-on card, with nothing configured.
        $this->assertStringContainsString('Single sign-on (disabled)', $crawler->filter('main')->text());
        $this->assertCount(0, $crawler->filter('input#oidcIssuerUrl'));
        $this->assertCount(1, $crawler->filter('a[href="https://docs.dreeve.app/integrations/single-sign-on/"]'));

        // The keys themselves must never reach the shared settings render context.
        $this->assertStringNotContainsString('drv', $crawler->filter('main')->text());

        // The settings navigation, with "Security" active.
        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Security', $selectedLink->text());
    }
}
