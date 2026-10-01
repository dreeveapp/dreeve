<?php

namespace App\Tests\Controller\Api\Internal\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class SearchActivitiesRequestHandlerTest extends ControllerWebTestCase
{
    public function testItReturnsAnEmptyResultForAnEmptyQuery(): void
    {
        $this->client->request('GET', '/api/internal/activities/search');
        $this->assertResponseIsSuccessful();
        $this->assertSame([], Json::decode((string) $this->client->getResponse()->getContent()));

        $this->client->request('GET', '/api/internal/activities/search?q=%20%20');
        $this->assertResponseIsSuccessful();
        $this->assertSame([], Json::decode((string) $this->client->getResponse()->getContent()));
    }

    public function testItFindsActivitiesByName(): void
    {
        $this->addActivity(id: '1', name: 'Morning commute', start: '2025-06-01 08:00:00', sportType: SportType::RIDE);
        $this->addActivity(id: '2', name: 'Evening run', start: '2025-06-02 18:00:00', sportType: SportType::RUN);

        $this->client->request('GET', '/api/internal/activities/search?q=commute');

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            [
                'value' => 'activity-1',
                'label' => 'Morning commute',
                'sublabel' => '2025-06-01 08:00 · Ride',
            ],
        ], Json::decode((string) $this->client->getResponse()->getContent()));
    }

    public function testItReturnsPrefixedActivityIdsSoTheyCanBeUsedInUrls(): void
    {
        $this->addActivity(id: '12345678', name: 'Morning commute', start: '2025-06-01 08:00:00', sportType: SportType::RIDE);

        $this->client->request('GET', '/api/internal/activities/search?q=34567');

        $this->assertResponseIsSuccessful();
        $results = Json::decode((string) $this->client->getResponse()->getContent());
        $this->assertCount(1, $results);
        $this->assertSame('activity-12345678', $results[0]['value']);
        $this->assertEquals(ActivityId::fromString($results[0]['value']), ActivityId::fromUnprefixed('12345678'));
    }

    public function testItOrdersResultsMostRecentFirst(): void
    {
        $this->addActivity(id: '1', name: 'Morning commute', start: '2025-06-01 08:00:00', sportType: SportType::RIDE);
        $this->addActivity(id: '2', name: 'Evening run', start: '2025-06-02 18:00:00', sportType: SportType::RUN);
        $this->addActivity(id: '3', name: 'Old ride', start: '2024-01-01 08:00:00', sportType: SportType::RIDE);

        $this->client->request('GET', '/api/internal/activities/search?q=2025-06');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['activity-2', 'activity-1'],
            array_column(Json::decode((string) $this->client->getResponse()->getContent()), 'value')
        );
    }

    public function testItLimitsTheNumberOfResults(): void
    {
        for ($i = 1; $i <= 11; ++$i) {
            $this->addActivity(id: (string) $i, name: 'Lunch ride '.$i, start: sprintf('2025-06-%02d 12:00:00', $i), sportType: SportType::RIDE);
        }

        $this->client->request('GET', '/api/internal/activities/search?q=lunch');

        $this->assertResponseIsSuccessful();
        $this->assertCount(10, Json::decode((string) $this->client->getResponse()->getContent()));
    }

    private function addActivity(string $id, string $name, string $start, SportType $sportType): void
    {
        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withName($name)
                ->withStartDateTime(SerializableDateTime::fromString($start))
                ->withSportType($sportType)
                ->build(),
            [],
        ));
    }
}
