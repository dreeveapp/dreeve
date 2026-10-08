<?php

namespace App\Tests\Controller\Page\Segment;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\DomCrawler\Crawler;

class SegmentRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/segments/segment-1');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringEndsWith(
            'segments.1',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        // Scoped to this segment, so importing an activity that rode another segment leaves it alone.
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, segments.1, gear, activities.9542782314',
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithHeartRateData(): void
    {
        $this->provideFullTestSet();
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
                ->build()
        );

        $this->client->request('GET', '/segments/segment-10');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithASingleHeartRateEffort(): void
    {
        $this->provideFullTestSet();
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
                ->build()
        );

        $crawler = $this->client->request('GET', '/segments/segment-10');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [['1', '01-10-23', '10s', '145']],
            $crawler->filter('#segmentTabsTopTen tbody tr')->each(
                fn (Crawler $row): array => [$row->filter('td')->eq(0)->text(), $row->filter('td')->eq(1)->text(), $row->filter('td')->eq(3)->text(), $row->filter('td')->eq(5)->text()],
            ),
        );
        $this->assertNotSame('[]', $crawler->filter('#segmentTabsEffortHeartRateChart [data-echarts-options]')->attr('data-echarts-options'));
    }

    public function testRenderWithoutAnyEfforts(): void
    {
        $this->provideFullTestSet();
        $this->addSegmentWithAPolylineFixtures();

        $crawler = $this->client->request('GET', '/segments/segment-10');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('#segmentTabsTopTen tbody tr'));
        $this->assertSame('[]', $crawler->filter('#segmentTabsEffortHeartRateChart [data-echarts-options]')->attr('data-echarts-options'));
    }

    public function testRenderWithWindAheadLink(): void
    {
        $this->provideFullTestSet();

        $segment = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('20'))
            ->withName(Name::fromString('Oude Kwaremont'))
            ->withPolyline(EncodedPolyline::fromString('tqafAua~y^vG{D'))
            ->build();
        $segmentRepository = $this->getContainer()->get(SegmentRepository::class);
        $segmentRepository->add($segment);
        $segmentRepository->update($segment);

        $this->client->request('GET', '/segments/segment-20');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="https://windahead.app/#polyline=tqafAua~y%5EvG%7BD&name=Oude%20Kwaremont"][target="_blank"]');
    }

    public function testRenderWithoutWindAheadLinkForVirtualSegment(): void
    {
        $this->provideFullTestSet();
        $this->addSegmentWithAPolylineFixtures();

        $this->client->request('GET', '/segments/segment-10');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('a[href^="https://windahead.app/#polyline="]');
    }

    public function testRenderWithStravaLink(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/segments/segment-1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1 a[href="https://www.strava.com/segments/1"]');
    }

    public function testRenderWithoutStravaLinkForCustomSegment(): void
    {
        $this->provideFullTestSet();
        $this->getContainer()->get(SegmentRepository::class)->add(
            SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed('custom'))
                ->withName(Name::fromString('Custom segment'))
                ->withType(SegmentType::CUSTOM)
                ->build()
        );

        $this->client->request('GET', '/segments/segment-custom');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Custom segment');
        $this->assertSelectorNotExists('h1 a');
    }

    public function testItDoesNotSwallowTheDataTableFragment(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/segments/data-table');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveASegmentThatDoesNotExist(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/segments/segment-999');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
