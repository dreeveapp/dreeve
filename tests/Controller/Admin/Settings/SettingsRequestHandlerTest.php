<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class SettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageFromTheIndex(): void
    {
        $this->client->request('GET', '/admin/settings');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRedirectsTheIndexToTheGeneralSettingsGroup(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings');
        $this->assertResponseRedirects('/admin/settings/general');
    }

    public function testItRendersAKnownSettingsGroup(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertSame(
            ['50', '61', '71', '81', '91'],
            $crawler->filter('input[name^="data[athlete][heartRateZones][zones]"][name$="[from]"]')
                ->each(fn ($node) => $node->attr('value')),
        );
    }

    public function testItReturns404ForAnUnknownGroup(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings/does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }
}
