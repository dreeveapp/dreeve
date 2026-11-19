<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\Internal\Segment;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class SegmentDataTableRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/segments/data-table');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertStringEndsWith(
            'segments.data-table',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, segments',
        );
        $this->assertResponseHeaderSame('Cache-Control', 'no-cache, private');
        $etag = (string) $this->client->getResponse()->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/internal/segments/data-table', server: ['HTTP_IF_NONE_MATCH' => $etag]);

        $this->assertResponseStatusCodeSame(304);
        $this->assertSame('', $this->client->getResponse()->getContent());
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
