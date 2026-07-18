<?php

namespace App\Tests\Controller\Admin\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Doctrine\DBAL\Connection;

class SearchActivitiesRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/activities/search');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItReturnsAnEmptyResultForAnEmptyQuery(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/activities/search');
        $this->assertResponseIsSuccessful();
        $this->assertSame([], Json::decode($this->client->getResponse()->getContent()));

        $this->client->request('GET', '/admin/activities/search?q=%20%20');
        $this->assertResponseIsSuccessful();
        $this->assertSame([], Json::decode($this->client->getResponse()->getContent()));
    }

    public function testItFindsActivitiesByName(): void
    {
        $this->addActivity(id: '1', name: 'Morning commute', start: '2025-06-01 08:00:00', sportType: SportType::RIDE);
        $this->addActivity(id: '2', name: 'Evening run', start: '2025-06-02 18:00:00', sportType: SportType::RUN);

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/admin/activities/search?q=commute');

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            [
                'value' => '1',
                'label' => 'Morning commute',
                'sublabel' => '2025-06-01 08:00 · Ride',
            ],
        ], Json::decode($this->client->getResponse()->getContent()));
    }

    public function testItFindsActivitiesByActivityId(): void
    {
        $this->addActivity(id: '12345678', name: 'Morning commute', start: '2025-06-01 08:00:00', sportType: SportType::RIDE);

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/admin/activities/search?q=34567');

        $this->assertResponseIsSuccessful();
        $results = Json::decode($this->client->getResponse()->getContent());
        $this->assertCount(1, $results);
        $this->assertSame('12345678', $results[0]['value']);
    }

    public function testItFindsActivitiesByDateAndOrdersThemMostRecentFirst(): void
    {
        $this->addActivity(id: '1', name: 'Morning commute', start: '2025-06-01 08:00:00', sportType: SportType::RIDE);
        $this->addActivity(id: '2', name: 'Evening run', start: '2025-06-02 18:00:00', sportType: SportType::RUN);
        $this->addActivity(id: '3', name: 'Old ride', start: '2024-01-01 08:00:00', sportType: SportType::RIDE);

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/admin/activities/search?q=2025-06');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['2', '1'],
            array_column(Json::decode($this->client->getResponse()->getContent()), 'value')
        );
    }

    public function testItFindsActivitiesWithAGeneratedName(): void
    {
        $this->addActivity(id: '1', name: 'Will be blanked', start: '2025-06-01 08:00:00', sportType: SportType::RUN);
        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE Activity SET name = :name WHERE activityId = :activityId',
            ['name' => '', 'activityId' => (string) ActivityId::fromUnprefixed('1')]
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/admin/activities/search?q=run');

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            [
                'value' => '1',
                'label' => 'Morning Run',
                'sublabel' => '2025-06-01 08:00 · Run',
            ],
        ], Json::decode($this->client->getResponse()->getContent()));
    }

    public function testItRequiresAllTokensToMatch(): void
    {
        $this->addActivity(id: '1', name: 'Morning commute', start: '2025-06-01 08:00:00', sportType: SportType::RIDE);
        $this->addActivity(id: '2', name: 'Morning run', start: '2024-03-03 08:00:00', sportType: SportType::RUN);

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/admin/activities/search?q=morning%202025');

        $this->assertResponseIsSuccessful();
        $results = Json::decode($this->client->getResponse()->getContent());
        $this->assertCount(1, $results);
        $this->assertSame('1', $results[0]['value']);
    }

    public function testItLimitsTheNumberOfResults(): void
    {
        for ($i = 1; $i <= 11; ++$i) {
            $this->addActivity(id: (string) $i, name: 'Lunch ride '.$i, start: sprintf('2025-06-%02d 12:00:00', $i), sportType: SportType::RIDE);
        }

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/admin/activities/search?q=lunch');

        $this->assertResponseIsSuccessful();
        $this->assertCount(10, Json::decode($this->client->getResponse()->getContent()));
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
