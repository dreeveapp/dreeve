<?php

namespace App\Tests\Controller\Api\V1\Activity;

use App\Domain\Api\Token;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Response;

class ActivitySearchRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private const string PATH = '/api/v1/activities';

    private Token $token;

    public function testItListsTheNewestActivitiesFirst(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', self::PATH, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItFiltersOnASingleLocalDay(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', self::PATH.'?filters[from]=2023-09-05&filters[to]=2023-09-05', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertEquals(['activity-9830227112'], array_column(Json::decode((string) $this->client->getResponse()->getContent())['activities'], 'id'));
    }

    public function testItIncludesBothEndsOfTheDateRange(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', self::PATH.'?filters[from]=2023-09-04&filters[to]=2023-09-11', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertEquals(
            ['activity-9830227182', 'activity-9830227112', 'activity-9830227167'],
            array_column(Json::decode((string) $this->client->getResponse()->getContent())['activities'], 'id')
        );
    }

    public function testItFiltersOnASingleSportType(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', self::PATH.'?filters[sportType]=Run', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertEquals(['activity-9756441741123', 'activity-45326441741'], array_column(Json::decode((string) $this->client->getResponse()->getContent())['activities'], 'id'));
    }

    public function testItFiltersOnMultipleCommaSeparatedSportTypes(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', self::PATH.'?filters[sportType]=Run,VirtualRide&filters[from]=2023-08-01', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertEquals(['activity-9756441741123', 'activity-9756441741'], array_column(Json::decode((string) $this->client->getResponse()->getContent())['activities'], 'id'));
    }

    public function testItFiltersOnActivitiesThatHaveGpx(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', self::PATH.'?filters[hasGpx]=true', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertEquals(['activity-9756441741'], array_column(Json::decode((string) $this->client->getResponse()->getContent())['activities'], 'id'));
    }

    public function testItPaginates(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', self::PATH.'?pagination[page]=2&pagination[size]=2', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertEquals(['activity-9830227112', 'activity-9830227167'], array_column(Json::decode((string) $this->client->getResponse()->getContent())['activities'], 'id'));
        $this->assertSame(
            ['page' => 2, 'size' => 2, 'total' => 15, 'totalPages' => 8],
            Json::decode((string) $this->client->getResponse()->getContent())['pagination']
        );
    }

    public function testItCountsOnlyTheFilteredActivities(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', self::PATH.'?filters[sportType]=Run&pagination[size]=1', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertSame(
            ['page' => 1, 'size' => 1, 'total' => 2, 'totalPages' => 2],
            Json::decode((string) $this->client->getResponse()->getContent())['pagination']
        );
    }

    #[DataProvider('provideInvalidQueryParameters')]
    public function testItRejectsInvalidQueryParameters(string $queryString): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', self::PATH.$queryString, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame(
            'bad_request',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
    }

    public static function provideInvalidQueryParameters(): iterable
    {
        yield 'a non existing date' => ['?filters[from]=2026-02-29'];
        yield 'a date that is not YYYY-MM-DD' => ['?filters[to]=31-12-2026'];
        yield 'from after to' => ['?filters[from]=2023-09-05&filters[to]=2023-09-04'];
        yield 'an unknown sport type' => ['?filters[sportType]=Quidditch'];
        yield 'a non boolean hasGpx' => ['?filters[hasGpx]=yes'];
        yield 'a page size above the maximum' => ['?pagination[size]=101'];
        yield 'a page number of zero' => ['?pagination[page]=0'];
    }

    public function testItReturnsASingleActivity(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', self::PATH.'/activity-9756441741', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItReportsAnUnknownActivityAsNotFound(): void
    {
        $this->client->request('GET', self::PATH.'/activity-1', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(
            'not_found',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
    }

    public function testItReportsAMalformedActivityIdAsNotFound(): void
    {
        $this->client->request('GET', self::PATH.'/not-an-activity-id', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(
            'not_found',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = Token::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}
