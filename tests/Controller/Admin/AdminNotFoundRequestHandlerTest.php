<?php

namespace App\Tests\Controller\Admin;

class AdminNotFoundRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/dmzdmzd');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheAdminNotFoundPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/dmzdmzd');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextContains('h1', '404');
        $this->assertSelectorExists('body.admin');
    }

    public function testItDoesNotShadowARealAdminPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings/athlete');

        $this->assertResponseIsSuccessful();
    }

    public function testItIsTheLastAdminRouteTheRouterTries(): void
    {
        $adminRouteNames = [];
        foreach ($this->getContainer()->get('router')->getRouteCollection() as $name => $route) {
            if (str_starts_with($route->getPath(), '/admin')) {
                $adminRouteNames[] = $name;
            }
        }

        $this->assertSame('admin_not_found', end($adminRouteNames));
    }
}
