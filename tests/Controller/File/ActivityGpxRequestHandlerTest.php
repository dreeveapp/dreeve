<?php

namespace App\Tests\Controller\File;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityGpxRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testHandle(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        assert($activityRepository instanceof ActivityRepository);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withName('Morning Ride')
                ->withSportType(SportType::RIDE)
                ->withStartDateTime(SerializableDateTime::fromString('2026-08-19 08:30:00'))
                ->build(),
            rawData: [],
        ));
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        assert($activityStreamRepository instanceof ActivityStreamRepository);
        foreach ([
            StreamType::LAT_LNG->value => [[51.2, 3.2], null, [51.21, 3.21]],
            StreamType::TIME->value => [0, 1, 2],
            StreamType::ALTITUDE->value => [10.5, 11.0, 11.5],
            StreamType::WATTS->value => [200, 210, 220],
            StreamType::HEART_RATE->value => [120, 121, 122],
            StreamType::CADENCE->value => [80, 81, 82],
            StreamType::TEMP->value => [20, 20, 21],
        ] as $streamType => $data) {
            $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType(StreamType::from($streamType))
                ->withData($data)
                ->build());
        }

        $this->client->request('GET', '/activities/activity-1/route.gpx');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/gpx+xml; charset=UTF-8');
        $this->assertMatchesXmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testHandleConvertsLocalStartDateToUtc(): void
    {
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Brussels');

        try {
            $activityRepository = $this->getContainer()->get(ActivityRepository::class);
            assert($activityRepository instanceof ActivityRepository);
            $activityRepository->add(ActivityWithRawData::fromState(
                activity: ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('1'))
                    ->withName('Morning Ride')
                    ->withSportType(SportType::RIDE)
                    ->withStartDateTime(SerializableDateTime::fromString('2026-08-19 08:30:00'))
                    ->build(),
                rawData: [],
            ));
            $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
            assert($activityStreamRepository instanceof ActivityStreamRepository);
            foreach ([
                StreamType::LAT_LNG->value => [[51.2, 3.2], null, [51.21, 3.21]],
                StreamType::TIME->value => [0, 1, 2],
                StreamType::ALTITUDE->value => [10.5, 11.0, 11.5],
                StreamType::WATTS->value => [200, 210, 220],
                StreamType::HEART_RATE->value => [120, 121, 122],
                StreamType::CADENCE->value => [80, 81, 82],
                StreamType::TEMP->value => [20, 20, 21],
            ] as $streamType => $data) {
                $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed('1'))
                    ->withStreamType(StreamType::from($streamType))
                    ->withData($data)
                    ->build());
            }

            $this->client->request('GET', '/activities/activity-1/route.gpx');

            $this->assertResponseIsSuccessful();
            $this->assertMatchesXmlSnapshot((string) $this->client->getResponse()->getContent());
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public function testHandleForActivityWithoutGps(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        assert($activityRepository instanceof ActivityRepository);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::ROWING)
                ->build(),
            rawData: [],
        ));
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        assert($activityStreamRepository instanceof ActivityStreamRepository);
        foreach ([StreamType::TIME, StreamType::HEART_RATE, StreamType::CADENCE] as $streamType) {
            $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType($streamType)
                ->withData([0, 1, 2])
                ->build());
        }

        $this->client->request('GET', '/activities/activity-1/route.gpx');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testHandleWhenActivityNotFound(): void
    {
        $this->client->request('GET', '/activities/activity-1/route.gpx');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextContains('h1', '404');
    }

    public function testItServesGpxFromTheEndpointAndNotFromTheBuildDirectory(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/activities/activity-9756441741/route.gpx');

        $this->assertEquals(
            'activity_gpx',
            $this->client->getRequest()->attributes->get('_route')
        );
    }
}
