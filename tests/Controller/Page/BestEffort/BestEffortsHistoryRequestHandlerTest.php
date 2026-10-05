<?php

namespace App\Tests\Controller\Page\BestEffort;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\Measurement\Length\ConvertableToMeter;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Snapshots\MatchesSnapshots;

class BestEffortsHistoryRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/best-efforts/Ride/10000');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringEndsWith(
            'best-efforts.Ride.10000',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, activities',
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItRendersEveryRideDistance(): void
    {
        $this->provideFullTestSet();

        $expected = [
            ['5 mi', '00:05'], ['10 km', '00:10'], ['10 mi', '00:10'], ['20 km', '00:20'],
            ['30 km', '00:30'], ['40 km', '00:40'], ['50 km', '00:50'], ['80 km', '01:20'],
            ['50 mi', '00:50'], ['90 km', '01:30'], ['100 km', '01:40'], ['100 mi', '01:40'],
        ];
        $distances = ActivityType::RIDE->getDistancesForBestEffortCalculation();
        $this->assertCount(count($expected), $distances);

        foreach ($distances as $index => $distance) {
            [$label, $bestTime] = $expected[$index];
            $this->client->request('GET', sprintf('/best-efforts/Ride/%d', $distance->toMeter()->toInt()));

            $this->assertResponseIsSuccessful();
            $content = (string) $this->client->getResponse()->getContent();
            $this->assertStringContainsString('<span>Best efforts - Cycling - '.$label.'</span>', $content);
            $this->assertStringContainsString('<div class="font-semibold text-gray-700">'.$bestTime.'</div>', $content);
        }
    }

    #[TestWith(['/best-efforts/Snorkeling/10000'])]
    #[TestWith(['/best-efforts/Walk/10000'])]
    #[TestWith(['/best-efforts/Ride/12345'])]
    public function testItDoesNotResolveAnUnknownBestEffort(string $path): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', $path);

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

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
