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

    public function testItReturns404ForAnUnknownGroup(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings/does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }
}
