<?php

namespace App\Tests\Controller\Api\V1\Metrics;

use App\Infrastructure\Security\Api\Token;
use App\Infrastructure\Security\Api\TokenType;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Response;

class StreaksRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private Token $token;

    #[DataProvider('provideQueryStrings')]
    public function testItReturnsTheStreaks(string $queryString): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/v1/metrics/streaks'.$queryString, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public static function provideQueryStrings(): iterable
    {
        yield 'all sport types' => [''];
        yield 'one sport type' => ['?filters[sportType]=Run'];
    }

    public function testItRejectsAnUnknownSportType(): void
    {
        $this->client->request('GET', '/api/v1/metrics/streaks?filters[sportType]=Quidditch', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame(
            'bad_request',
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
        $this->token = Token::generate(TokenType::API);
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}
