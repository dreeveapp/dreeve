<?php

namespace App\Tests\Application\Import\StravaImport\ImportActivities\Pipeline;

use App\Application\Import\StravaImport\ImportActivities\Pipeline\ActivityImportContext;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\FetchActivityStreams;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Scan\ActivityScan;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Strava\Strava;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;

class FetchActivityStreamsTest extends ContainerTestCase
{
    private FetchActivityStreams $fetchActivityStreams;
    private MockObject $strava;

    public function testWhenStreamAlreadyExists(): void
    {
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withStreamType(StreamType::DISTANCE)
                ->build()
        );

        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed('test'),
            rawStravaData: [],
            isNewActivity: true
        );

        $this->strava
            ->expects($this->once())
            ->method('getAllActivityStreams')
            ->willReturn([
                [
                    'type' => 'distance',
                ],
            ]);

        $this->fetchActivityStreams->process($context);
    }

    public function testItSkipsWhenStreamsWereAlreadyImported(): void
    {
        $this->getContainer()->get(ActivityScanRepository::class)->add(
            ActivityScan::create(activityId: ActivityId::fromUnprefixed('test'), type: ActivityScanType::STREAMS)
        );

        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed('test'),
            rawStravaData: [],
            isNewActivity: false
        );

        $this->strava
            ->expects($this->never())
            ->method('getAllActivityStreams');

        $this->assertEquals(
            $context,
            $this->fetchActivityStreams->process($context)
        );
    }

    public function testProcessWhen404(): void
    {
        $this->strava
            ->expects($this->once())
            ->method('getAllActivityStreams')
            ->willThrowException(new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(404, [], Json::encode(['error' => 'The error']))));

        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed('test'),
            rawStravaData: [],
            isNewActivity: true
        )->withStreamsWereImported();

        $this->assertEquals(
            $context,
            $this->fetchActivityStreams->process($context)
        );
    }

    public function testProcessWhenException(): void
    {
        $theException = new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(500, [], Json::encode(['error' => 'The error'])));
        $this->strava
            ->expects($this->once())
            ->method('getAllActivityStreams')
            ->willThrowException($theException);

        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed('test'),
            rawStravaData: [],
            isNewActivity: true
        );

        $this->expectExceptionObject($theException);
        $this->fetchActivityStreams->process($context);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->fetchActivityStreams = new FetchActivityStreams(
            activityScanRepository: $this->getContainer()->get(ActivityScanRepository::class),
            activityStreamRepository: $this->getContainer()->get(ActivityStreamRepository::class),
            strava: $this->strava = $this->createMock(Strava::class),
            clock: PausedClock::fromString('2025-12-18'),
        );
    }
}
