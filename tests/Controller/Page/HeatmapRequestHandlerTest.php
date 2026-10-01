<?php

namespace App\Tests\Controller\Page;

use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class HeatmapRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/heatmap');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/heatmap');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'heatmap.auth=anon',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItIsTaggedWithWhatItRenders(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/heatmap');

        $this->assertResponseHeaderSame('X-Dreeve-Cache-Tags', 'settings.appearance, settings.general, activity.route, settings.maps');
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

        $this->client->request('GET', '/heatmap');
        $this->assertStringNotContainsString(
            'admin/settings/maps',
            (string) $this->client->getResponse()->getContent(),
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/heatmap');
        $this->assertStringContainsString(
            'admin/settings/maps?redirectTo=%2Fheatmap',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testItVariesByAuthentication(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/heatmap');
        $anonymousCacheKey = (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key');
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=0, must-revalidate, no-store, private');

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/heatmap');

        $this->assertNotEquals(
            $anonymousCacheKey,
            $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }
}
