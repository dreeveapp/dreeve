<?php

namespace App\Tests\Controller\Api\V1\Metrics;

use App\Infrastructure\Security\Api\ApiToken;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Response;

class TrainingVolumeRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private ApiToken $token;

    #[DataProvider('provideQueryStrings')]
    public function testItReturnsTheTrainingVolumeForADateRange(string $queryString): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/v1/metrics/training-volume'.$queryString, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public static function provideQueryStrings(): iterable
    {
        yield 'a month' => ['?filters[from]=2023-09-01&filters[to]=2023-09-30'];
        yield 'a single day' => ['?filters[from]=2023-09-05&filters[to]=2023-09-05'];
        yield 'a year for one sport type' => ['?filters[from]=2023-01-01&filters[to]=2023-12-31&filters[sportType]=Run'];
        yield 'a range without activities' => ['?filters[from]=2023-10-09&filters[to]=2023-10-15'];
    }

    #[DataProvider('provideInvalidQueryStrings')]
    public function testItRejectsInvalidQueryParameters(string $queryString): void
    {
        $this->client->request('GET', '/api/v1/metrics/training-volume'.$queryString, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame(
            'bad_request',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
    }

    public static function provideInvalidQueryStrings(): iterable
    {
        yield 'no dates' => [''];
        yield 'no from' => ['?filters[to]=2023-09-30'];
        yield 'no to' => ['?filters[from]=2023-09-01'];
        yield 'a date that is not YYYY-MM-DD' => ['?filters[from]=01-09-2023&filters[to]=2023-09-30'];
        yield 'from after to' => ['?filters[from]=2023-09-05&filters[to]=2023-09-04'];
        yield 'an unknown sport type' => ['?filters[from]=2023-09-01&filters[to]=2023-09-30&filters[sportType]=Quidditch'];
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = ApiToken::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}
