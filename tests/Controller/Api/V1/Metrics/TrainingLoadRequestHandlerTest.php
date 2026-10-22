<?php

namespace App\Tests\Controller\Api\V1\Metrics;

use App\Infrastructure\Security\Api\Token;
use App\Infrastructure\Security\Api\TokenType;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class TrainingLoadRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private Token $token;

    public function testItReturnsTheCurrentTrainingLoad(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/v1/metrics/training-load', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
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
