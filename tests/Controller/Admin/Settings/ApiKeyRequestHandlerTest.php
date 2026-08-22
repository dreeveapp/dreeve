<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class ApiKeyRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/api-key/generate');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersAKey(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/api-key/generate');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->assertStringContainsString('Generate an API key', $crawler->filter('h3')->text());
        $this->assertMatchesRegularExpression('/^drv_[a-f0-9]{64}$/', $crawler->filter('.modal-like-form code')->first()->text());
        $this->assertStringContainsString('DREEVE_API_KEY', $crawler->filter('main')->text());
        // Nothing is submitted anywhere, the value only becomes real once it is in .env.
        $this->assertCount(0, $crawler->filter('.modal-like-form form'));
    }

    public function testItRendersADifferentKeyEveryTime(): void
    {
        $this->client->loginUser($this->adminUser());

        $first = $this->client->request('GET', '/admin/settings/api-key/generate')->filter('.modal-like-form code')->first()->text();
        $second = $this->client->request('GET', '/admin/settings/api-key/generate')->filter('.modal-like-form code')->first()->text();

        $this->assertNotSame($first, $second);
    }
}
