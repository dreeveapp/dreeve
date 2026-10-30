<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateBestActivityEfforts;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\SpyOutput;
use Spatie\Snapshots\MatchesSnapshots;

class CalculateBestActivityEffortsTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private CalculateBestActivityEfforts $calculateBestActivityEfforts;

    public function testProcess(): void
    {
        $output = new SpyOutput();
        $this->provideSomeData();

        $this->calculateBestActivityEfforts->process($output);

        $this->assertMatchesJsonSnapshot(
            Json::encode($this->getConnection()
                ->executeQuery('SELECT * FROM ActivityBestEffort')->fetchAllAssociative())
        );

        $this->calculateBestActivityEfforts->process($output);
        $this->assertStringEndsWith("  => Calculated best efforts for 16 activities (3 s)\n", (string) $output);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateBestActivityEfforts = $this->getContainer()->get(CalculateBestActivityEfforts::class);
    }

    private function provideSomeData(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(-1))
                ->withSportType(SportType::RUN)
                ->withDistance(Kilometer::from(200))
                ->build(), []
        ));
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(-1))
                ->withStreamType(StreamType::TIME)
                ->withData([1, 2, 3])
                ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(-1))
                ->withStreamType(StreamType::DISTANCE)
                ->withData([1, 2, 3])
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(0))
                ->withSportType(SportType::ALPINE_SKI)
                ->withDistance(Kilometer::from(200))
                ->build(), []
        ));
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(0))
                ->withStreamType(StreamType::TIME)
                ->withData([1, 2, 3])
                ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(0))
                ->withStreamType(StreamType::DISTANCE)
                ->withData([1, 2, 3])
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1))
                ->withDistance(Kilometer::from(200))
                ->build(), []
        ));
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1))
                ->withStreamType(StreamType::TIME)
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/activity-1-time.json')))
                ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1))
                ->withStreamType(StreamType::DISTANCE)
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/activity-1-distance.json')))
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->withDistance(Kilometer::from(20))
                ->build(), []
        ));
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->withStreamType(StreamType::TIME)
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/activity-2-time.json')))
                ->build()
        );
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->withStreamType(StreamType::DISTANCE)
                ->withData(Json::decode((string) file_get_contents(__DIR__.'/fixtures/activity-2-distance.json')))
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(3))
                ->build(), []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->build(), []
        ));
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(4))
                ->withStreamType(StreamType::TIME)
                ->withData([])
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(5))
                ->build(), []
        ));
        $this->getContainer()->get(ActivityStreamRepository::class)->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(5))
                ->withStreamType(StreamType::DISTANCE)
                ->withData([])
                ->build()
        );

        for ($i = 6; $i < 20; ++$i) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i))
                    ->withDistance(Kilometer::from(3))
                    ->withSportType(SportType::RUN)
                    ->build(), []
            ));
            $this->getContainer()->get(ActivityStreamRepository::class)->add(
                ActivityStreamBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i))
                    ->withStreamType(StreamType::TIME)
                    ->withData([288, 296, 303, 309, 316, 323, 330, 340, 347, 349, 350, 351, 352, 353, 354, 356, 357, 362, 368, 374, 380, 383, 388, 390, 396, 403, 410, 416, 425, 427, 429, 432, 440, 449, 450, 458, 459, 463, 466, 473, 481, 487, 493, 500, 509, 510, 518, 526, 535, 541, 547, 555, 562, 570, 576, 582, 583, 587, 591, 593, 599, 600, 602, 603, 604, 606, 607, 608, 611, 613, 619, 622, 624, 627, 634, 635, 637, 645, 648])
                    ->build()
            );
            $this->getContainer()->get(ActivityStreamRepository::class)->add(
                ActivityStreamBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($i))
                    ->withStreamType(StreamType::DISTANCE)
                    ->withData([1813.9, 1866, 1911.4, 1948.7, 1991.4, 2034.9, 2076.3, 2134.9, 2176.7, 2189, 2195.3, 2201.4, 2207.5, 2213.8, 2219.8, 2232, 2238, 2269.3, 2306, 2344.5, 2382, 2401.1, 2430.4, 2442.9, 2483.1, 2531.4, 2577.4, 2612.8, 2672, 2685.5, 2699.3])
                    ->build()
            );
        }
    }
}
