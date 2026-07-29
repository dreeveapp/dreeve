<?php

namespace App\Tests\Controller;

use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\Admin\AdminWebTestCase;

class AuthStatusRequestHandlerTest extends AdminWebTestCase
{
    public function testItIsNotAuthenticatedForAnonymousUsers(): void
    {
        $this->client->request('GET', '/auth/status');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['isAuthenticated' => false], Json::decode($this->client->getResponse()->getContent()));
    }

    public function testItIsAuthenticatedForLoggedInUsers(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/auth/status');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['isAuthenticated' => true], Json::decode($this->client->getResponse()->getContent()));
    }

    #[\Override]
    protected function shouldMarkAppAsBuilt(): bool
    {
        return false;
    }
}
