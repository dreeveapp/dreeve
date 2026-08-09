<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\SegmentApiRequestHandler;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\String\Name;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class SegmentApiRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testPolylines(): void
    {
        $this->provideSegmentWithAPolyline();

        $this->client->request('GET', '/api/segment/segment-10/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testPolylinesItShouldServeTheSecondRequestFromTheRenderCache(): void
    {
        $this->provideSegmentWithAPolyline();

        // The array cache adapter is reset between HTTP requests, so drive the handler directly
        // to observe a second, cached call.
        $handler = static::getContainer()->get(SegmentApiRequestHandler::class);

        $firstResponse = $handler->polylines('segment-10');
        $this->assertEquals('MISS', $firstResponse->headers->get('X-Cache'));
        $this->assertStringEndsWith('segment.10.polylines', (string) $firstResponse->headers->get('X-Cache-Key'));
        // A segment polyline never changes, so nothing beyond the cross cutting tags applies.
        $this->assertEquals(
            'settings.appearance, settings.general',
            $firstResponse->headers->get('X-Cache-Tags'),
        );

        $secondResponse = $handler->polylines('segment-10');

        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
        $this->assertEquals($firstResponse->getContent(), $secondResponse->getContent());
        $this->assertEquals(
            $firstResponse->headers->get('X-Cache-Key'),
            $secondResponse->headers->get('X-Cache-Key'),
        );
    }

    public function testPolylinesForSegmentWithoutAMap(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/segment/segment-1/polylines');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Segment "segment-1" has no map'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    public function testPolylinesWhenSegmentNotFound(): void
    {
        $this->client->request('GET', '/api/segment/segment-1/polylines');

        $this->assertResponseStatusCodeSame(404);
        $this->assertEquals(
            ['message' => 'Segment "segment-1" not found'],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    public function testItOutranksTheCatchAllApiRoute(): void
    {
        $this->provideSegmentWithAPolyline();

        $this->client->request('GET', '/api/segment/segment-10/polylines');

        $this->assertEquals(
            'api_segment_polylines',
            $this->client->getRequest()->attributes->get('_route')
        );
    }

    private function provideSegmentWithAPolyline(): void
    {
        $this->provideFullTestSet();

        $segment = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('10'))
            ->withName(Name::fromString('Segment Ten'))
            ->withDistance(Kilometer::from(0.1))
            ->withDeviceName('MyWhoosh')
            ->withSportType(SportType::VIRTUAL_RIDE)
            ->withPolyline(EncodedPolyline::fromString('tqafAua~y^vG{D'))
            ->build();

        $segmentRepository = static::getContainer()->get(SegmentRepository::class);
        // add() never writes the polyline, it only lands through update().
        $segmentRepository->add($segment);
        $segmentRepository->update($segment);
    }
}
