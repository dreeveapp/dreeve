<?php

namespace App\Tests\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\Measurement\Length\ConvertableToMeter;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideBuiltTestSet;
use Spatie\Snapshots\MatchesSnapshots;

class BestEffortsHistoryFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideBuiltTestSet;

    public function testRender(): void
    {
        $this->provideBuiltTestSet();

        foreach (ActivityType::RIDE->getDistancesForBestEffortCalculation() as $distance) {
            $this->client->request('GET', sprintf(
                '/api/fragment/page/best-efforts/%s/%d',
                ActivityType::RIDE->value,
                $distance->toMeter()->toInt(),
            ));

            $this->assertResponseIsSuccessful();
            $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
            $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
        }
    }

    public function testGetPath(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/best-efforts/Ride/10000');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Morning Ride', (string) $this->client->getResponse()->getContent());
        $this->assertStringEndsWith(
            'best-efforts.Ride.10000',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, activities',
        );
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/data/best-efforts/Ride/10000');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityTypeThatDoesNotExist(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/best-efforts/Snorkeling/10000');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityTypeWithoutBestEfforts(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/best-efforts/Walk/10000');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveADistanceThatIsNotCalculated(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/best-efforts/Ride/12345');

        $this->assertResponseStatusCodeSame(404);
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
}
