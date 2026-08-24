<?php

namespace App\Tests\Domain\Dashboard;

use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class DashboardFragmentTest extends AdminWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/fragment/page/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItIsNotServedAsAPartialFragment(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/fragment/partial/dashboard');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItOnlyRendersTheEditLinkForAuthenticatedVisitors(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/fragment/page/dashboard');
        $this->assertStringNotContainsString(
            'admin/settings/dashboard',
            (string) $this->client->getResponse()->getContent(),
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/api/internal/fragment/page/dashboard');
        $this->assertStringContainsString(
            'admin/settings/dashboard?redirectTo=%2Fdashboard',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testItVariesByAuthentication(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/fragment/page/dashboard');
        $anonymousCacheKey = (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key');
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=0, must-revalidate, no-store, private');

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/api/internal/fragment/page/dashboard');

        $this->assertNotEquals(
            $anonymousCacheKey,
            $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItIsTaggedWithTheDashboardItRenders(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/fragment/page/dashboard');

        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, dashboard',
        );
    }
}
