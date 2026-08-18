<?php

namespace App\Tests\Domain\Activity\Route\Match\FindRouteMatches;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\Match\FindRouteMatches\FindRouteMatches;
use App\Domain\Activity\Route\Match\RouteMatch;
use App\Domain\Activity\Route\Signature\ActivityRouteSignatureRepository;
use App\Domain\Activity\Route\Signature\RouteCells;
use App\Domain\Activity\Route\Signature\RouteWaypoints;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorldType;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Route\Signature\ActivityRouteSignatureBuilder;

class FindRouteMatchesQueryHandlerTest extends ContainerTestCase
{
    private QueryBus $queryBus;

    public function testHandle(): void
    {
        $route = range(1, 20);

        $this->addActivityWithRouteCells('subject', $route);
        $this->addActivityWithRouteCells('faster', $route, movingTimeInSeconds: 3000);
        $this->addActivityWithRouteCells('slower', [...range(1, 16), 901, 902, 903, 904], movingTimeInSeconds: 4200);
        $this->addActivityWithRouteCells('other-route', [...range(1, 10), ...range(901, 910)]);
        $this->addActivityWithRouteCells('other-activity-type', $route, sportType: SportType::RUN);
        $this->addActivityWithRouteCells('other-world-type', $route, worldType: WorldType::ZWIFT);
        $this->addActivityWithRouteCells('too-far', $route, distance: Kilometer::from(20));
        $this->addActivityWithRouteCells('reversed', $route, waypoints: self::reversedWaypoints());
        $this->addActivityWithRouteCells('without-signature', null);

        $routeMatches = $this->queryBus->ask(new FindRouteMatches(ActivityId::fromUnprefixed('subject')))->getRouteMatches();

        $this->assertEquals(
            [
                ['activity-faster', 1, 3000, false],
                ['activity-subject', 2, 3600, true],
                ['activity-slower', 3, 4200, false],
            ],
            array_map(
                fn (RouteMatch $routeMatch): array => [
                    (string) $routeMatch->getActivityId(),
                    $routeMatch->getRank(),
                    $routeMatch->getMovingTimeInSeconds(),
                    $routeMatch->isCurrentActivity(),
                ],
                $routeMatches->toArray()
            )
        );
    }

    public function testHandleDoesNotMatchALongerRouteThatMerelyContainsTheSubject(): void
    {
        $this->addActivityWithRouteCells('subject', range(1, 20));
        $this->addActivityWithRouteCells('longer-route', range(1, 27));

        $routeMatches = $this->queryBus->ask(new FindRouteMatches(ActivityId::fromUnprefixed('subject')))->getRouteMatches();

        $this->assertEquals(
            ['activity-subject'],
            array_map(fn (RouteMatch $routeMatch): string => (string) $routeMatch->getActivityId(), $routeMatches->toArray())
        );
    }

    public function testHandleDoesNotMatchALongRouteThatDriftsMoreThanTheAbsoluteMaximum(): void
    {
        $route = range(1, 20);
        $forward = ActivityRouteSignatureBuilder::fromDefaults()->build()->getWaypoints()->toArray();
        $drifted = array_map(fn (int $coordinate, int $index): int => 0 === $index % 2 ? $coordinate + 1500 : $coordinate, $forward, array_keys($forward));

        $this->addActivityWithRouteCells('subject', $route, distance: Kilometer::from(80));
        $this->addActivityWithRouteCells('drifted', $route, distance: Kilometer::from(80), waypoints: RouteWaypoints::fromArray($drifted));

        $routeMatches = $this->queryBus->ask(new FindRouteMatches(ActivityId::fromUnprefixed('subject')))->getRouteMatches();

        $this->assertEquals(
            ['activity-subject'],
            array_map(fn (RouteMatch $routeMatch): string => (string) $routeMatch->getActivityId(), $routeMatches->toArray())
        );
    }

    public function testHandleForAnActivityWithTooFewCells(): void
    {
        $this->addActivityWithRouteCells('subject', range(1, 5));
        $this->addActivityWithRouteCells('other', range(1, 5));

        $this->assertEmpty(
            $this->queryBus->ask(new FindRouteMatches(ActivityId::fromUnprefixed('subject')))->getRouteMatches()->toArray()
        );
    }

    public function testHandleForAnActivityWithoutASignature(): void
    {
        $this->addActivityWithRouteCells('subject', null);

        $this->assertEmpty(
            $this->queryBus->ask(new FindRouteMatches(ActivityId::fromUnprefixed('subject')))->getRouteMatches()->toArray()
        );
    }

    private static function reversedWaypoints(): RouteWaypoints
    {
        $forward = ActivityRouteSignatureBuilder::fromDefaults()->build()->getWaypoints()->toArray();
        $reversed = [];
        for ($i = count($forward) - 2; $i >= 0; $i -= 2) {
            $reversed[] = $forward[$i];
            $reversed[] = $forward[$i + 1];
        }

        return RouteWaypoints::fromArray($reversed);
    }

    /**
     * @param list<int>|null $cells
     */
    private function addActivityWithRouteCells(
        string $activityId,
        ?array $cells,
        int $movingTimeInSeconds = 3600,
        SportType $sportType = SportType::RIDE,
        WorldType $worldType = WorldType::REAL_WORLD,
        ?Kilometer $distance = null,
        ?RouteWaypoints $waypoints = null,
    ): void {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withName($activityId)
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
                ->withMovingTimeInSeconds($movingTimeInSeconds)
                ->withSportType($sportType)
                ->withWorldType($worldType)
                ->withDistance($distance ?? Kilometer::from(10))
                ->build(),
            []
        ));

        if (is_null($cells)) {
            return;
        }

        $this->getContainer()->get(ActivityRouteSignatureRepository::class)->add(
            ActivityRouteSignatureBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withCells(RouteCells::fromArray($cells))
                ->withWaypoints($waypoints ?? ActivityRouteSignatureBuilder::fromDefaults()->build()->getWaypoints())
                ->build()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->queryBus = $this->getContainer()->get(QueryBus::class);
    }
}
