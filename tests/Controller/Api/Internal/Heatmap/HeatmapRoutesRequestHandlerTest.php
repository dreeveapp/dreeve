<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\Internal\Heatmap;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\RouteRepository;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class HeatmapRoutesRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/heatmap/routes');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertResponseHasHeader('ETag');
        $this->assertResponseHeaderSame('Cache-Control', 'no-cache, private');
        $this->assertStringContainsString('Night Ride', (string) $this->client->getResponse()->getContent());
        $this->assertStringEndsWith(
            'heatmap.routes',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, activity.route',
        );
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testConditionalRequestRevalidatesAndInvalidates(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/heatmap/routes');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHasHeader('ETag');
        $this->assertResponseHeaderSame('Cache-Control', 'no-cache, private');
        $etag = (string) $this->client->getResponse()->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->client->request('GET', '/api/internal/heatmap/routes', server: [
            'HTTP_IF_NONE_MATCH' => $etag,
        ]);

        $this->assertResponseStatusCodeSame(304);
        $this->assertSame('', (string) $this->client->getResponse()->getContent());
        $this->assertResponseHeaderSame('ETag', $etag);
        $this->assertResponseHeaderSame('Cache-Control', 'no-cache, private');

        $route = $this->getContainer()->get(RouteRepository::class)->findAll()->getFirst();
        $this->assertNotNull($route);
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $activity = $activityRepository->find($route->getActivityId());
        $activityRepository->update(ActivityWithRawData::fromState(
            $activity->withPolyline('kqafAua~y^vG{D'),
            [],
        ));

        $this->client->request('GET', '/api/internal/heatmap/routes', server: [
            'HTTP_IF_NONE_MATCH' => $etag,
        ]);

        $this->assertResponseIsSuccessful();
        $newEtag = (string) $this->client->getResponse()->headers->get('ETag');
        $this->assertNotSame($etag, $newEtag);
        $this->assertResponseHeaderSame('Cache-Control', 'no-cache, private');
        $this->assertNotEmpty((string) $this->client->getResponse()->getContent());
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
