<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\Internal\Heatmap;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class HeatmapCountriesRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/heatmap/countries');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertResponseHasHeader('ETag');
        $this->assertResponseHeaderSame('Cache-Control', 'no-cache, private');
        $this->assertStringEndsWith(
            'heatmap.countries',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, activity.route',
        );
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());

        $etag = (string) $this->client->getResponse()->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->client->request('GET', '/api/internal/heatmap/countries', server: [
            'HTTP_IF_NONE_MATCH' => $etag,
        ]);

        $this->assertResponseStatusCodeSame(304);
        $this->assertSame('', (string) $this->client->getResponse()->getContent());
        $this->assertResponseHeaderSame('ETag', $etag);
        $this->assertResponseHeaderSame('Cache-Control', 'no-cache, private');
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
