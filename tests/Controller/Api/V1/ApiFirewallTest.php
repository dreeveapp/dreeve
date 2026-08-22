<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Domain\Api\Token;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Security\AuthenticatedVisitor;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class ApiFirewallTest extends ControllerWebTestCase
{
    private Token $token;

    #[DataProvider('provideRequestsWithoutAValidToken')]
    public function testItRejectsARequestWithoutAValidToken(array $server): void
    {
        $this->client->request('GET', '/api/v1/status', server: $server);

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('Bearer realm="Dreeve"', $response->headers->get('WWW-Authenticate'));
        $this->assertSame('invalid_token', Json::decode((string) $response->getContent())['error']);
    }

    public static function provideRequestsWithoutAValidToken(): iterable
    {
        yield 'no Authorization header' => [[]];
        yield 'another token' => [['HTTP_AUTHORIZATION' => 'Bearer drv_'.str_repeat('a', 64)]];
        yield 'empty bearer token' => [['HTTP_AUTHORIZATION' => 'Bearer ']];
        yield 'another scheme' => [['HTTP_AUTHORIZATION' => 'Basic YWRtaW46YWRtaW4=']];
        yield 'token shape the extractor does not match' => [['HTTP_AUTHORIZATION' => 'Bearer drv:whatever']];
    }

    #[DataProvider('provideUnroutedPaths')]
    public function testItAnswersJsonForAnUnroutedPath(string $path): void
    {
        $this->client->request('GET', $path, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame([
            'error' => 'not_found',
            'message' => 'This API endpoint does not exist.',
        ], Json::decode((string) $response->getContent()));
    }

    public static function provideUnroutedPaths(): iterable
    {
        yield 'the prefix itself' => ['/api/v1'];
        yield 'a nested path' => ['/api/v1/nope'];
        yield 'a deeply nested path' => ['/api/v1/activity/upload/nope'];
    }

    public function testItDoesNotClaimALookalikePrefix(): void
    {
        $this->client->request('GET', '/api/v10/status');

        // The app shell answers this one, the API firewall must not turn it into a 401.
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('text/html', (string) $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testItDoesNotStartASession(): void
    {
        $this->client->request('GET', '/api/v1/status', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertSame([], $this->client->getResponse()->headers->getCookies());
    }

    public function testItDoesNotLeakTheApiUserIntoTheNextRequest(): void
    {
        $this->client->request('GET', '/api/v1/status', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);
        $this->assertTrue($this->getContainer()->get(AuthenticatedVisitor::class)->isAuthenticated());

        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertFalse($this->getContainer()->get(AuthenticatedVisitor::class)->isAuthenticated());
    }

    public function testItAnswers401RatherThanRedirectingToTheLoginPage(): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::SECURITY->keyValueKey(),
            Value::fromString(Json::encode(['requiresAuthentication' => true])),
        ));

        $this->client->request('GET', '/api/v1/status');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testItRejectsEverythingWhenNoKeyIsConfigured(): void
    {
        $this->withApiKey('');

        $this->client->request('GET', '/api/v1/status', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testItRejectsEverythingWhenTheConfiguredKeyIsMalformed(): void
    {
        // Strict format validation makes this reachable: the key is set, but unusable.
        $this->withApiKey('replace-me');

        $this->client->request('GET', '/api/v1/status', server: ['HTTP_AUTHORIZATION' => 'Bearer replace-me']);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        // Runs before createClient(), so the container is built with the key already in place.
        $this->token = Token::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}
