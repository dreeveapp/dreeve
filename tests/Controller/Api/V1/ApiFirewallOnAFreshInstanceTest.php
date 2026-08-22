<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Domain\Api\Token;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiFirewallOnAFreshInstanceTest extends ControllerWebTestCase
{
    private Token $token;

    public function testItAnswersJsonRatherThanRedirectingToTheSetup(): void
    {
        $this->client->request('GET', '/api/v1/status', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        // Pairing a device is the first thing a user does, so this has to work before the athlete
        // is configured and before a single activity exists.
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('dreeve', Json::decode((string) $response->getContent())['app']);
    }

    public function testItStillRejectsAnAnonymousRequest(): void
    {
        $this->client->request('GET', '/api/v1/status');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testTheRestOfTheAppKeepsRedirectingToTheSetup(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseRedirects('http://localhost/admin/settings/athlete');
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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->clear(SettingsGroup::GENERAL->keyValueKey());
    }
}
