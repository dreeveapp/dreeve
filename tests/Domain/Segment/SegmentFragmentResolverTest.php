<?php

namespace App\Tests\Domain\Segment;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class SegmentFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/fragment/page/segments/segment-1');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithHeartRateData(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();
        $this->addSegmentWithAPolylineFixtures();

        $segmentEffortRepository = $this->getContainer()->get(SegmentEffortRepository::class);
        $segmentEffortRepository->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('11'))
                ->withSegmentId(SegmentId::fromUnprefixed('10'))
                ->withActivityId(ActivityId::fromUnprefixed('9542782314'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-01'))
                ->withElapsedTimeInSeconds(10.3)
                ->withAverageWatts(200)
                ->withAverageHeartRate(145)
                ->withDistance(Kilometer::from(0.1))
                ->withName('An effort')
                ->build()
        );
        $segmentEffortRepository->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('12'))
                ->withSegmentId(SegmentId::fromUnprefixed('10'))
                ->withActivityId(ActivityId::fromUnprefixed('9542782314'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-02'))
                ->withElapsedTimeInSeconds(11.3)
                ->withAverageWatts(200)
                ->withAverageHeartRate(162)
                ->withDistance(Kilometer::from(0.1))
                ->withName('An effort')
                ->build()
        );

        $this->client->request('GET', '/api/internal/fragment/page/segments/segment-10');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithASingleHeartRateEffort(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();
        $this->addSegmentWithAPolylineFixtures();

        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('11'))
                ->withSegmentId(SegmentId::fromUnprefixed('10'))
                ->withActivityId(ActivityId::fromUnprefixed('9542782314'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-01'))
                ->withElapsedTimeInSeconds(10.3)
                ->withAverageWatts(200)
                ->withAverageHeartRate(145)
                ->withDistance(Kilometer::from(0.1))
                ->withName('An effort')
                ->build()
        );

        $this->client->request('GET', '/api/internal/fragment/page/segments/segment-10');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithoutAnyEfforts(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/api/internal/fragment/page/segments/segment-10');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/fragment/page/segments/segment-1');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'segments.1',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItIsTaggedWithTheSegmentAndTheActivitiesItLists(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/fragment/page/segments/segment-1');

        // Scoped to this segment, so importing an activity that rode another segment leaves it alone.
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, segments.1, gear, activities.9542782314',
        );
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/fragment/data/segments/segment-1');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotSwallowTheDataTableFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/fragment/page/segments/data-table');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveASegmentThatDoesNotExist(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/fragment/page/segments/segment-999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnprefixedSegmentId(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/fragment/page/segments/1');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
