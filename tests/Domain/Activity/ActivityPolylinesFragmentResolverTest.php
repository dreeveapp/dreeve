<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityPolylinesFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/activity-9830227112/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/activity-9830227112/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'activity.9830227112.polylines',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, activities.9830227112',
        );
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9830227112/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItPrefersTheLatLngStreamOverTheEncodedPolyline(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();
        $this->addLatLngStreamFor(
            ActivityId::fromUnprefixed('9830227112'),
            [[51.2, 3.18], [51.21, 3.19], [51.22, 3.2]],
        );

        $this->client->request('GET', '/api/fragment/data/activity/activity-9830227112/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertEquals(
            [[[51.2, 3.18], [51.21, 3.19], [51.22, 3.2]]],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    public function testItFallsBackToTheEncodedPolylineForAnEmptyLatLngStream(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/activity-9830227112/polylines');
        $fromPolyline = (string) $this->client->getResponse()->getContent();

        $this->addLatLngStreamFor(ActivityId::fromUnprefixed('9830227112'), []);

        $this->client->request('GET', '/api/fragment/data/activity/activity-9830227112/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertEquals($fromPolyline, $this->client->getResponse()->getContent());
    }

    public function testItDoesNotResolveAnActivityWithoutAMap(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/activity-9756441741/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityThatDoesNotExist(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/activity-1/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnprefixedActivityId(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/fragment/data/activity/9830227112/polylines');

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * @param array<mixed> $data
     */
    private function addLatLngStreamFor(ActivityId $activityId, array $data): void
    {
        /** @var ActivityStreamRepository $activityStreamRepository */
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::LAT_LNG)
                ->withData($data)
                ->build()
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
