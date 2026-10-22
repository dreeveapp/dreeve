<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class GenerateTokenRequestHandlerTest extends AdminWebTestCase
{
    #[DataProvider('provideKeys')]
    public function testItRendersAKey(string $url, string $title, string $keyPattern, string $envVar): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', $url);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->assertStringContainsString($title, $crawler->filter('h3')->text());
        $this->assertMatchesRegularExpression($keyPattern, $crawler->filter('.modal-like-form code')->first()->text());
        $this->assertStringContainsString('Set '.$envVar.' in your .env file', $crawler->filter('main')->text());
        // Nothing is submitted anywhere, the value only becomes real once it is in .env.
        $this->assertCount(0, $crawler->filter('.modal-like-form form'));
    }

    public static function provideKeys(): iterable
    {
        yield 'API key' => ['/admin/settings/token/generate/api', 'Generate an API key', '/^drv_[a-f0-9]{64}$/', 'DREEVE_API_KEY'];
        yield 'MCP key' => ['/admin/settings/token/generate/mcp', 'Generate an MCP key', '/^drvm_[a-f0-9]{64}$/', 'DREEVE_MCP_KEY'];
    }

    public function testItRendersADifferentKeyEveryTime(): void
    {
        $this->client->loginUser($this->adminUser());

        $first = $this->client->request('GET', '/admin/settings/token/generate/api')->filter('.modal-like-form code')->first()->text();
        $second = $this->client->request('GET', '/admin/settings/token/generate/api')->filter('.modal-like-form code')->first()->text();

        $this->assertNotSame($first, $second);
    }
}
