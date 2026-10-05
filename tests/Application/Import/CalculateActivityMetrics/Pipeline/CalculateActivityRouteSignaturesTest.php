<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateActivityRouteSignatures;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\SpyOutput;

class CalculateActivityRouteSignaturesTest extends ContainerTestCase
{
    private CalculateActivityRouteSignatures $calculateActivityRouteSignatures;

    public function testProcess(): void
    {
        $output = new SpyOutput();
        $this->provideSomeData();

        $this->calculateActivityRouteSignatures->process($output);

        $this->assertEquals(
            [
                ['activityId' => 'activity--1', 'polylineChecksum' => '30214f23', 'cellCount' => 21],
                ['activityId' => 'activity--2', 'polylineChecksum' => '5e5c0957', 'cellCount' => 31],
            ],
            $this->getConnection()
                ->executeQuery('SELECT activityId, polylineChecksum, cellCount FROM ActivityRouteSignature ORDER BY activityId')
                ->fetchAllAssociative(),
        );

        $this->calculateActivityRouteSignatures->process($output);
        $this->assertSame(
            "  => Calculated route signatures for 0 activities (3 s)\n"
            ."  => Calculated route signatures for 1 activities (3 s)\n"
            ."  => Calculated route signatures for 2 activities (3 s)\n"
            ."  => Calculated route signatures for 2 activities (3 s)\n",
            (string) $output,
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateActivityRouteSignatures = $this->getContainer()->get(CalculateActivityRouteSignatures::class);
    }

    private function provideSomeData(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(-1))
                ->withPolyline((string) EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.02, 3.02]]))
                ->build(), []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(-2))
                ->withPolyline((string) EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.03, 3.0]]))
                ->build(), []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(-3))
                ->withPolyline(null)
                ->build(), []
        ));
    }
}
