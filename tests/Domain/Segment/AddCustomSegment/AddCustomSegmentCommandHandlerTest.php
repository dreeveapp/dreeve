<?php

declare(strict_types=1);

namespace App\Tests\Domain\Segment\AddCustomSegment;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Segment\AddCustomSegment\AddCustomSegment;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\String\Name;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class AddCustomSegmentCommandHandlerTest extends ContainerTestCase
{
    private const string FIXTURES = __DIR__.'/../SegmentEffort/Matching/fixtures/';

    private CommandBus $commandBus;

    public function testHandle(): void
    {
        $streams = Json::decode(file_get_contents(self::FIXTURES.'exact-pass.json') ?: '');
        $altitude = [];
        $currentAltitude = 0.0;
        foreach (array_keys($streams['time']) as $index) {
            $altitude[] = $currentAltitude;
            $currentAltitude += $index >= 100 && $index < 110 ? 1.2 : 0.4;
        }
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::RIDE)
                ->build(),
            []
        ));
        foreach ([StreamType::LAT_LNG, StreamType::DISTANCE, StreamType::TIME] as $streamType) {
            $this->getContainer()->get(ActivityStreamRepository::class)->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType($streamType)
                ->withData($streams[$streamType->value])
                ->build());
        }
        $this->getContainer()->get(ActivityStreamRepository::class)->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withStreamType(StreamType::ALTITUDE)
            ->withData($altitude)
            ->build());

        $this->commandBus->dispatch(AddCustomSegment::fromPayload([
            'activityId' => 'activity-1',
            'startIndex' => 37,
            'endIndex' => 162,
            'name' => 'Custom segment',
            'isFavourite' => true,
        ]));

        $segments = $this->getContainer()->get(SegmentRepository::class)->findByType(SegmentType::CUSTOM);
        $this->assertCount(1, $segments);
        $segment = $segments->getFirst();
        $this->assertInstanceOf(Segment::class, $segment);
        $this->assertEquals(Name::fromString('Custom segment'), $segment->getOriginalName());
        $this->assertSame(SportType::RIDE, $segment->getSportType());
        $this->assertEquals(Kilometer::from(1), $segment->getDistance());
        $this->assertSame(5.8, $segment->getAverageGradient());
        $this->assertSame(15.0, $segment->getMaxGradient());
        $this->assertTrue($segment->isFavourite());
        $this->assertSame('be', $segment->getCountryCode());
        $this->assertEquals(
            EncodedPolyline::fromCoordinates(array_slice($streams['latlng'], 37, 126)),
            $segment->getPolyline()
        );
    }

    #[DataProvider(methodName: 'provideUnprocessableCommands')]
    public function testHandleThrows(array $streamTypes, int $startIndex, int $endIndex, string $expectedReason): void
    {
        $streams = Json::decode(file_get_contents(self::FIXTURES.'exact-pass.json') ?: '');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->build(),
            []
        ));
        foreach ($streamTypes as $streamType) {
            $this->getContainer()->get(ActivityStreamRepository::class)->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType($streamType)
                ->withData($streams[$streamType->value])
                ->build());
        }

        $this->expectExceptionObject(CouldNotProcessCommand::withReason($expectedReason));

        $this->commandBus->dispatch(AddCustomSegment::fromPayload([
            'activityId' => 'activity-1',
            'startIndex' => $startIndex,
            'endIndex' => $endIndex,
            'name' => 'Custom segment',
        ]));
    }

    public static function provideUnprocessableCommands(): iterable
    {
        yield 'no GPS data' => [[StreamType::DISTANCE], 37, 162, 'The selected activity has no GPS data.'];
        yield 'end outside the route' => [[StreamType::LAT_LNG, StreamType::DISTANCE], 37, 500, 'The selected start and end are not part of the activity route.'];
        yield 'too short' => [[StreamType::LAT_LNG, StreamType::DISTANCE], 37, 40, 'A segment needs to be at least 100 meters long.'];
    }

    public function testHandleThrowsWhenActivityDoesNotExist(): void
    {
        $this->expectExceptionObject(CouldNotProcessCommand::withReason('The selected activity does not exist.'));

        $this->commandBus->dispatch(AddCustomSegment::fromPayload([
            'activityId' => 'activity-1',
            'startIndex' => 37,
            'endIndex' => 162,
            'name' => 'Custom segment',
        ]));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
