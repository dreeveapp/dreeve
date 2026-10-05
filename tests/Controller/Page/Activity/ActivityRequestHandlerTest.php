<?php

namespace App\Tests\Controller\Page\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Lap\ActivityLapId;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Lap\ActivityLapBuilder;
use App\Tests\Domain\Activity\Split\ActivitySplitBuilder;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/activities/activity-9756441741');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringEndsWith(
            'activities.9756441741.auth=anon',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, activities.9756441741, gear',
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderForAVirtualRide(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/activities/activity-9542782314');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    #[DataProvider('provideSportTypesWithTheirOwnTemplate')]
    public function testItRendersTheTemplateOfTheSportType(SportType $sportType): void
    {
        $this->provideFullTestSet();

        $activityId = ActivityId::fromUnprefixed('123456789');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withSportType($sportType)
                ->withIsCommute(true)
                ->withIsGroupActivity(true)
                ->build(),
            [],
        ));
        $this->getContainer()->get(ActivityLapRepository::class)->add(
            ActivityLapBuilder::fromDefaults()
                ->withLapId(ActivityLapId::fromUnprefixed('123456789-1'))
                ->withActivityId($activityId)
                ->build()
        );
        $this->getContainer()->get(ActivitySplitRepository::class)->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withGapPace(SecPerKm::from(310))
                ->build()
        );

        $this->client->request('GET', '/activities/'.$activityId);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public static function provideSportTypesWithTheirOwnTemplate(): \Generator
    {
        yield 'run' => [SportType::RUN];
        yield 'swim' => [SportType::POOL_SWIM];
    }

    public function testItRendersTheHeartRateWithoutTimeInZoneWhenThereAreNoHeartRateStreams(): void
    {
        $this->provideFullTestSet();

        $activityId = ActivityId::fromUnprefixed('123456789');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withImportSource(ImportSource::MANUAL)
                ->withSportType(SportType::WEIGHT_TRAINING)
                ->withAverageHeartRate(141)
                ->withMaxHeartRate(173)
                ->build(),
            [],
        ));

        $this->client->request('GET', '/activities/'.$activityId);

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('>141</div>', $content);
        $this->assertStringContainsString('173', $content);
        $this->assertStringNotContainsString('Time in zone', $content);
    }

    public function testItLabelsTheVelocityDistributionOfARunAsPace(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/activities/activity-45326441741');

        $this->assertResponseIsSuccessful();
        $this->assertDistributionChartIsTitled('Pace');
    }

    public function testItLabelsTheVelocityDistributionOfARideAsSpeed(): void
    {
        $this->provideFullTestSet();

        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: ActivityId::fromUnprefixed('9756441741'),
            streamType: StreamType::VELOCITY,
            metricType: ActivityStreamMetricType::VALUE_DISTRIBUTION,
            data: [10 => 5, 20 => 9, 30 => 4],
        ));

        $this->client->request('GET', '/activities/activity-9756441741');

        $this->assertResponseIsSuccessful();
        $this->assertDistributionChartIsTitled('Speed');
    }

    private function assertDistributionChartIsTitled(string $title): void
    {
        $this->assertStringContainsString(
            sprintf('<div class="text-sm font-semibold mb-2 text-center">%s</div>', $title),
            (string) $this->client->getResponse()->getContent(),
        );
    }

    #[DataProvider('provideUrlsThatAreNotFound')]
    public function testItIsNotFound(string $url): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', $url);

        $this->assertResponseStatusCodeSame(404);
    }

    public static function provideUrlsThatAreNotFound(): \Generator
    {
        yield 'an activity that does not exist' => ['/activities/activity-1'];
        yield 'the unprefixed id is not a valid activity id' => ['/activities/9756441741'];
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
