<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Domain\Api\KeyValueBasedTokenRepository;
use App\Domain\Api\StoredToken;
use App\Domain\Api\Token;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiFirewallOnAFreshInstanceTest extends ControllerWebTestCase
{
    private Token $token;

    public function testItAnswersJsonRatherThanRedirectingToTheSetup(): void
    {
        $this->client->request('GET', '/api/v1/status', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('not_found', Json::decode((string) $response->getContent())['error']);
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
    protected function setUp(): void
    {
        parent::setUp();

        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->clear(SettingsGroup::GENERAL->keyValueKey());

        $this->token = Token::generate();
        (new KeyValueBasedTokenRepository($keyValueStore))->save(
            StoredToken::create($this->token->hash(), SerializableDateTime::some()),
        );
    }
}
