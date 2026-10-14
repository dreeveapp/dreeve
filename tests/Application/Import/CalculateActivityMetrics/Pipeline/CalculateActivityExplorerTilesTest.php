<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateActivityExplorerTiles;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Scan\ActivityScan;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\SpyOutput;

class CalculateActivityExplorerTilesTest extends ContainerTestCase
{
    public function testProcess(): void
    {
        $output = new SpyOutput();
        foreach ([
            'ride' => [SportType::RIDE, true],
            'run' => [SportType::RUN, true],
            'virtual-ride' => [SportType::VIRTUAL_RIDE, true],
            'streams-not-fetched' => [SportType::RIDE, false],
        ] as $activityId => [$sportType, $streamsFetched]) {
            $activityId = ActivityId::fromUnprefixed($activityId);
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId($activityId)
                    ->withSportType($sportType)
                    ->withPolyline((string) EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.02, 3.02]]))
                    ->build(),
                []
            ));
            if ($streamsFetched) {
                $this->getContainer()->get(ActivityScanRepository::class)->add(
                    ActivityScan::create(activityId: $activityId, type: ActivityScanType::STREAMS)
                );
            }
        }

        $calculateActivityExplorerTiles = $this->getContainer()->get(CalculateActivityExplorerTiles::class);
        $calculateActivityExplorerTiles->process($output);
        $calculateActivityExplorerTiles->process($output);

        $this->assertSame(
            ['activity-ride', 'activity-run'],
            $this->getConnection()->executeQuery('SELECT activityId FROM ActivityExplorerTiles ORDER BY activityId')->fetchFirstColumn()
        );
        $this->assertSame(
            "  => Calculated explorer tiles for 0 activities (3 s)\n"
            ."  => Calculated explorer tiles for 1 activities (3 s)\n"
            ."  => Calculated explorer tiles for 2 activities (3 s)\n"
            ."  => Calculated explorer tiles for 2 activities (3 s)\n",
            (string) $output,
        );
    }
}
