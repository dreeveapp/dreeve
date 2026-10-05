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

        $this->client->request('GET', '/heatmap');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringEndsWith(
            'heatmap.auth=anon',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame('X-Dreeve-Cache-Tags', 'settings.appearance, settings.general, activity.route, settings.maps');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
