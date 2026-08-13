<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class TrainingLoadFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/page/training-load');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithAPolarisedTrainingTrend(): void
    {
        $this->provideFullTestSet();

        // The fixture set has no heart rate data inside the last 30 days, so both rolling windows are
        // empty and no trend can be shown. The clock is paused at 2023-10-17 16:15:04: an activity on
        // 2023-10-17 only counts towards the current window and one on 2023-09-17 only towards
        // yesterday's, which is what makes the zone distribution shift overnight.
        $this->addActivityWithHeartRateDistribution(
            activityId: ActivityId::fromUnprefixed('42'),
            heartRateDistribution: [180 => 600],
            startDate: SerializableDateTime::fromString('2023-10-17 10:00:00'),
        );
        $this->addActivityWithHeartRateDistribution(
            activityId: ActivityId::fromUnprefixed('43'),
            heartRateDistribution: [100 => 2400],
            startDate: SerializableDateTime::fromString('2023-09-17 10:00:00'),
        );

        $this->client->request('GET', '/api/fragment/page/training-load');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    /**
     * @param array<int, int> $heartRateDistribution
     */
    private function addActivityWithHeartRateDistribution(
        ActivityId $activityId,
        array $heartRateDistribution,
        SerializableDateTime $startDate,
    ): void {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withSportType(SportType::RIDE)
                ->withStartDateTime($startDate)
                ->build(),
            [],
        ));
        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::HEART_RATE,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: $heartRateDistribution,
        ));
    }

    public function testItIsNotServedAsAPartialFragment(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/partial/training-load');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsTaggedWithTheActivitiesItRenders(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/page/training-load');

        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, activities',
        );
    }
}
