<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\Internal\Activity;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityDataTableRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/activities/data-table');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertStringEndsWith(
            'activities.data-table',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, activities, settings.athleteWeight',
        );
        $this->assertResponseHeaderSame('Cache-Control', 'no-cache, private');
        $etag = (string) $this->client->getResponse()->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/internal/activities/data-table', server: ['HTTP_IF_NONE_MATCH' => $etag]);

        $this->assertResponseStatusCodeSame(304);
        $this->assertSame('', $this->client->getResponse()->getContent());
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
