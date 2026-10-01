<?php

namespace App\Tests\Controller\Page;

use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class EddingtonRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/eddington');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/eddington');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'eddington.auth=anon',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItIsTaggedWithWhatItRenders(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/eddington');

        $this->assertResponseHeaderSame('X-Dreeve-Cache-Tags', 'settings.appearance, settings.general, activities, settings.metrics');
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }

    public function testItOnlyRendersTheAdminLinkForAuthenticatedVisitors(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/eddington');
        $this->assertStringNotContainsString(
            'admin/settings/metrics',
            (string) $this->client->getResponse()->getContent(),
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/eddington');
        $this->assertStringContainsString(
            'admin/settings/metrics?redirectTo=%2Feddington',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testItVariesByAuthentication(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/eddington');
        $anonymousCacheKey = (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key');
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=0, must-revalidate, no-store, private');

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/eddington');

        $this->assertNotEquals(
            $anonymousCacheKey,
            $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }
}
