<?php

declare(strict_types=1);

namespace App\Tests\Controller\Mcp;

use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsName;
use App\Infrastructure\Security\Api\ApiToken;
use App\Infrastructure\Security\Mcp\McpToken;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Response;

class McpServerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;

    private const string PROTOCOL_VERSION = '2025-11-25';

    private McpToken $token;

    #[DataProvider('provideRequestsWithoutAValidToken')]
    public function testItRejectsARequestWithoutAValidToken(array $server): void
    {
        $this->client->request('POST', '/mcp', server: $server, content: Json::encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]));

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('Bearer realm="Dreeve"', $response->headers->get('WWW-Authenticate'));
        $this->assertSame('invalid_token', Json::decode((string) $response->getContent())['error']);
    }

    public static function provideRequestsWithoutAValidToken(): iterable
    {
        yield 'no Authorization header' => [[]];
        yield 'another token' => [['HTTP_AUTHORIZATION' => 'Bearer drvm_'.str_repeat('a', 64)]];
    }

    public function testItRejectsTheApiKey(): void
    {
        $apiKey = ApiToken::generate();
        $this->withApiKey((string) $apiKey);

        $this->client->request('POST', '/mcp', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$apiKey]);

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertSame('Bearer realm="Dreeve", error="insufficient_scope"', $response->headers->get('WWW-Authenticate'));
        $this->assertSame('insufficient_scope', Json::decode((string) $response->getContent())['error']);
    }

    public function testItRejectsEverythingWhenNoKeyIsConfigured(): void
    {
        $this->withMcpKey('');

        $this->postToMcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testItAnswers401RatherThanRedirectingToTheLoginPage(): void
    {
        /** @var DbalSettingsRepository $settingsRepository */
        $settingsRepository = $this->getContainer()->get(DbalSettingsRepository::class);
        $settingsRepository->save(SettingsName::REQUIRES_AUTHENTICATION, true);

        $this->client->request('POST', '/mcp');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testItInitializesASession(): void
    {
        $response = $this->postToMcp([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
            ],
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotEmpty($response->headers->get('Mcp-Session-Id'));
        $this->assertSame([], $response->headers->getCookies());
        $this->assertMatchesJsonSnapshot((string) $response->getContent());
    }

    public function testItListsTheTools(): void
    {
        $response = $this->postToMcp(
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
            $this->initializeSession(),
        );

        $this->assertMatchesJsonSnapshot((string) $response->getContent());
    }

    #[DataProvider('provideToolCalls')]
    public function testItCallsATool(string $name, array $arguments): void
    {
        $response = $this->postToMcp(
            [
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => (object) $arguments],
            ],
            $this->initializeSession(),
        );

        $this->assertMatchesJsonSnapshot((string) $response->getContent());
    }

    public static function provideToolCalls(): iterable
    {
        yield 'get_athlete_details' => ['get_athlete_details', []];
        yield 'get_heart_rate_zones for a sport type' => ['get_heart_rate_zones', ['sportType' => 'Run']];
        yield 'get_activity_by_id with a prefixed id' => ['get_activity_by_id', ['activityId' => 'activity-903645']];
        yield 'get_activity_by_id for an unknown activity' => ['get_activity_by_id', ['activityId' => 'activity-1']];
        yield 'search_activities with filters' => ['search_activities', ['sportType' => 'Ride', 'hasGpx' => false, 'page' => 1, 'size' => 10]];
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = McpToken::generate();
        $_SERVER['DREEVE_MCP_KEY'] = $_ENV['DREEVE_MCP_KEY'] = (string) $this->token;
    }

    private function initializeSession(): string
    {
        $response = $this->postToMcp([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
            ],
        ]);

        return (string) $response->headers->get('Mcp-Session-Id');
    }

    private function postToMcp(array $message, ?string $sessionId = null): Response
    {
        $server = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
        ];
        if (null !== $sessionId) {
            $server['HTTP_MCP_SESSION_ID'] = $sessionId;
            $server['HTTP_MCP_PROTOCOL_VERSION'] = self::PROTOCOL_VERSION;
        }

        $this->client->request('POST', '/mcp', server: $server, content: Json::encode($message));

        return $this->client->getResponse();
    }
}
