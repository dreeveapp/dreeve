<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\BestEffortsApiRequestHandler;
use App\Domain\Activity\ActivityType;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Measurement\Length\ConvertableToMeter;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BestEffortsApiRequestHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private BestEffortsApiRequestHandler $bestEffortsApiRequestHandler;

    public function testHistory(): void
    {
        $this->provideFullTestSet();

        foreach (ActivityType::RIDE->getDistancesForBestEffortCalculation() as $distance) {
            $response = $this->bestEffortsApiRequestHandler->history(
                activityType: ActivityType::RIDE->value,
                distanceInMeter: (string) $distance->toMeter()->toInt(),
            );

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
            $this->assertMatchesHtmlSnapshot((string) $response->getContent());
        }
    }

    public function testHistoryItShouldServeTheSecondRequestFromTheRenderCache(): void
    {
        $this->provideFullTestSet();

        $firstResponse = $this->bestEffortsApiRequestHandler->history('Ride', '10000');
        $this->assertStringContainsString('Morning Ride', (string) $firstResponse->getContent());
        $this->assertEquals('MISS', $firstResponse->headers->get('X-Cache'));
        $this->assertStringEndsWith('best-efforts.Ride.10000', (string) $firstResponse->headers->get('X-Cache-Key'));
        $this->assertEquals(
            'settings.appearance, settings.general, activities',
            $firstResponse->headers->get('X-Cache-Tags'),
        );

        $this->getConnection()->executeStatement(
            'UPDATE Activity SET name = "This name never made it into the render cache"'
        );

        $secondResponse = $this->bestEffortsApiRequestHandler->history('Ride', '10000');
        $this->assertEquals($firstResponse->getContent(), $secondResponse->getContent());
        $this->assertEquals('HIT', $secondResponse->headers->get('X-Cache'));
    }

    public function testHistoryWhenTheActivityTypeDoesNotExist(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('Activity type "Snorkeling" not found');

        $this->bestEffortsApiRequestHandler->history('Snorkeling', '10000');
    }

    public function testHistoryWhenTheActivityTypeHasNoBestEfforts(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('Best efforts for "Walk" over 10000 meter not found');

        $this->bestEffortsApiRequestHandler->history(ActivityType::WALK->value, '10000');
    }

    public function testHistoryWhenTheDistanceIsNotCalculated(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('Best efforts for "Ride" over 12345 meter not found');

        $this->bestEffortsApiRequestHandler->history(ActivityType::RIDE->value, '12345');
    }

    public function testEveryCalculatedDistanceIsAddressable(): void
    {
        foreach (ActivityType::cases() as $activityType) {
            $distancesInMeter = array_map(
                fn (ConvertableToMeter $distance): int => $distance->toMeter()->toInt(),
                $activityType->getDistancesForBestEffortCalculation()
            );

            $this->assertEquals(
                array_unique($distancesInMeter),
                $distancesInMeter,
                sprintf('Two distances of "%s" resolve to the same amount of meter', $activityType->value)
            );
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(RenderCache::class)->clear();
        $this->bestEffortsApiRequestHandler = $this->getContainer()->get(BestEffortsApiRequestHandler::class);
    }
}
