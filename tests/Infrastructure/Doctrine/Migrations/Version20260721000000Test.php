<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\DbalActivityRepository;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\DbalActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Polyline;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20260721000000;
use Psr\Log\NullLogger;

// Migration files are not on the (dev) autoloader; the doctrine-migrations bundle
// loads them via its own finder at runtime. Require it directly so we can unit-test it.
require_once __DIR__.'/../../../../migrations/Version20260721000000.php';

class Version20260721000000Test extends ContainerTestCase
{
    private ActivityRepository $activityRepository;
    private ActivityStreamRepository $activityStreamRepository;

    public function testUpRecomputesOverSimplifiedPolylinesForFileImportsOnly(): void
    {
        $coordinates = $this->buildLoopCoordinates();

        // A collapsed 2-point polyline, as produced by the buggy simplify(0.4) tolerance.
        $collapsedPolyline = (string) EncodedPolyline::fromCoordinates([
            $coordinates[0],
            $coordinates[count($coordinates) - 1],
        ]);

        // File-imported activity: has the collapsed polyline stored + an intact latlng stream.
        $fitActivityId = ActivityId::fromUnprefixed('1');
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($fitActivityId)
                ->withImportSource(ImportSource::FIT_FILE)
                ->withPolyline($collapsedPolyline)
                ->build(),
            ['raw' => 'data'],
        ));
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($fitActivityId)
                ->withStreamType(StreamType::LAT_LNG)
                ->withData($coordinates)
                ->build()
        );

        // Strava-imported activity: must be left untouched.
        $stravaActivityId = ActivityId::fromUnprefixed('2');
        $stravaPolyline = (string) Polyline::fromCoordinates($coordinates)->simplify()->encode();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($stravaActivityId)
                ->withImportSource(ImportSource::STRAVA_API)
                ->withPolyline($stravaPolyline)
                ->build(),
            ['raw' => 'data'],
        ));
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($stravaActivityId)
                ->withStreamType(StreamType::LAT_LNG)
                ->withData($coordinates)
                ->build()
        );

        $migration = new Version20260721000000($this->getConnection(), new NullLogger());
        $migration->up(new Schema());

        // The file-imported activity's polyline was recomputed from the full stream.
        $recomputed = $this->getStoredPolyline($fitActivityId);
        $this->assertGreaterThan(
            10,
            count(EncodedPolyline::fromString($recomputed)->decodeAndPairLatLng()),
        );

        // The Strava activity's polyline is unchanged.
        $this->assertSame($stravaPolyline, $this->getStoredPolyline($stravaActivityId));
    }

    /**
     * @return array<int, array{float, float}>
     */
    private function buildLoopCoordinates(): array
    {
        $centerLat = 51.2194;
        $centerLng = 4.4025;
        $radius = 0.03;
        $numberOfPoints = 200;

        $coordinates = [];
        for ($i = 0; $i < $numberOfPoints; ++$i) {
            $angle = 2 * M_PI * $i / $numberOfPoints;
            $coordinates[] = [
                $centerLat + $radius * sin($angle),
                $centerLng + $radius * cos($angle),
            ];
        }

        return $coordinates;
    }

    private function getStoredPolyline(ActivityId $activityId): string
    {
        return $this->getConnection()->executeQuery(
            'SELECT polyline FROM Activity WHERE activityId = :activityId',
            ['activityId' => $activityId],
        )->fetchOne();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = new DbalActivityRepository($this->getConnection());
        $this->activityStreamRepository = new DbalActivityStreamRepository($this->getConnection());
    }
}
