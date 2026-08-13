<?php

namespace App\Tests\Domain\Activity\Route\Signature;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\Signature\ActivityRouteSignatureRepository;
use App\Domain\Activity\Route\Signature\DbalActivityRouteSignatureRepository;
use App\Domain\Activity\Route\Signature\RouteCells;
use App\Domain\Activity\Route\Signature\RouteGrid;
use App\Domain\Activity\Route\Signature\RouteWaypoints;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class DbalActivityRouteSignatureRepositoryTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ActivityRouteSignatureRepository $activityRouteSignatureRepository;
    private RouteGrid $routeGrid;

    public function testAdd(): void
    {
        $this->activityRouteSignatureRepository->add(
            ActivityRouteSignatureBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->withPolylineChecksum('aaaaaaaa')
                ->withCells(RouteCells::fromArray([10, 20, 30]))
                ->withWaypoints(RouteWaypoints::fromArray([5100000, 300000, 5100100, 300100]))
                ->build()
        );
        $this->activityRouteSignatureRepository->add(
            ActivityRouteSignatureBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->withPolylineChecksum('bbbbbbbb')
                ->withCells(RouteCells::fromArray([40]))
                ->build()
        );

        $results = $this->getConnection()
            ->executeQuery('SELECT activityId, polylineChecksum, cellCount FROM ActivityRouteSignature ORDER BY activityId')
            ->fetchAllAssociative();

        $this->assertMatchesJsonSnapshot(Json::encode($results));
        $this->assertEquals(
            [10, 20, 30],
            Json::uncompressAndDecode($this->getConnection()->executeQuery(
                'SELECT cells FROM ActivityRouteSignature WHERE activityId = "activity-test"'
            )->fetchOne())
        );
        $this->assertEquals(
            [5100000, 300000, 5100100, 300100],
            Json::uncompressAndDecode($this->getConnection()->executeQuery(
                'SELECT waypoints FROM ActivityRouteSignature WHERE activityId = "activity-test"'
            )->fetchOne())
        );
    }

    public function testFindActivityIdsThatNeedRouteSignatureCalculation(): void
    {
        $polyline = (string) EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.01, 3.01]]);

        $this->addActivity('up-to-date', $polyline);
        $this->activityRouteSignatureRepository->add(
            ActivityRouteSignatureBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('up-to-date'))
                ->withPolylineChecksum($this->routeGrid->checksumFor(EncodedPolyline::fromString($polyline)))
                ->build()
        );

        $this->addActivity('rerouted', $polyline);
        $this->activityRouteSignatureRepository->add(
            ActivityRouteSignatureBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('rerouted'))
                ->withPolylineChecksum('deadbeef')
                ->build()
        );

        $this->addActivity('never-calculated', $polyline);
        $this->addActivity('without-polyline', null);
        $this->addActivity('with-empty-polyline', '');

        $this->assertEquals(
            ActivityIds::fromArray([
                ActivityId::fromUnprefixed('never-calculated'),
                ActivityId::fromUnprefixed('rerouted'),
            ]),
            $this->activityRouteSignatureRepository->findActivityIdsThatNeedRouteSignatureCalculation()
        );
    }

    public function testDeleteForActivity(): void
    {
        $this->activityRouteSignatureRepository->add(
            ActivityRouteSignatureBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test'))
                ->build()
        );
        $this->activityRouteSignatureRepository->add(
            ActivityRouteSignatureBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->build()
        );

        $this->activityRouteSignatureRepository->deleteForActivity(ActivityId::fromUnprefixed('test'));

        $this->assertEquals(
            1,
            $this->getConnection()->executeQuery('SELECT COUNT(*) FROM ActivityRouteSignature')->fetchOne()
        );
    }

    private function addActivity(string $activityId, ?string $polyline): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withPolyline($polyline)
                    ->build(),
                []
            )
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->routeGrid = new RouteGrid();
        $this->activityRouteSignatureRepository = new DbalActivityRouteSignatureRepository(
            $this->getConnection(),
            $this->routeGrid,
        );
    }
}
