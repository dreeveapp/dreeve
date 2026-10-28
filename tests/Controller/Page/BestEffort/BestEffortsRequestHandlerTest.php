<?php

namespace App\Tests\Controller\Page\BestEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\BestEffort\ActivityBestEffortBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class BestEffortsRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('run-1'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-01 10:00:00'))
                ->withSportType(SportType::RUN)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityBestEffortRepository::class)->add(
            ActivityBestEffortBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('run-1'))
                ->withSportType(SportType::RUN)
                ->withDistanceInMeter(Meter::from(5000))
                ->withTimeInSeconds(1500)
                ->build()
        );

        $this->client->request('GET', '/best-efforts');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringEndsWith(
            'best-efforts',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItShouldExpireAtMidnight(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/best-efforts');
        $this->assertResponseHeaderSame('X-Dreeve-Cache-TTL', '27896');
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
