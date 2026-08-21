<?php

declare(strict_types=1);

namespace App\Tests\Domain\Api;

use App\Domain\Api\KeyValueBasedTokenRepository;
use App\Domain\Api\StoredToken;
use App\Domain\Api\Token;
use App\Domain\Api\TokenRepository;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class KeyValueBasedTokenRepositoryTest extends ContainerTestCase
{
    private TokenRepository $tokenRepository;
    private KeyValueStore $keyValueStore;

    public function testFindWhenNoTokenHasBeenGenerated(): void
    {
        $this->assertNull($this->tokenRepository->find());
    }

    public function testSaveAndFind(): void
    {
        $token = Token::generate();
        $createdOn = SerializableDateTime::fromString('2026-08-21 09:12:00');

        $this->tokenRepository->save(StoredToken::create($token->hash(), $createdOn));

        $found = $this->tokenRepository->find();
        $this->assertNotNull($found);
        $this->assertTrue($found->getHash()->matches((string) $token));
        $this->assertEquals($createdOn, $found->getCreatedOn());
    }

    public function testSaveStoresTheHashAndNotTheToken(): void
    {
        $token = Token::generate();

        $this->tokenRepository->save(StoredToken::create($token->hash(), SerializableDateTime::some()));

        $this->assertStringNotContainsString(
            (string) $token,
            (string) $this->keyValueStore->find(Key::API_TOKEN),
        );
    }

    public function testSaveReplacesThePreviousToken(): void
    {
        $previousToken = Token::generate();
        $this->tokenRepository->save(StoredToken::create($previousToken->hash(), SerializableDateTime::some()));

        $newToken = Token::generate();
        $this->tokenRepository->save(StoredToken::create($newToken->hash(), SerializableDateTime::some()));

        $found = $this->tokenRepository->find();
        $this->assertNotNull($found);
        $this->assertTrue($found->getHash()->matches((string) $newToken));
        $this->assertFalse($found->getHash()->matches((string) $previousToken));
    }

    public function testRevoke(): void
    {
        $this->tokenRepository->save(StoredToken::create(Token::generate()->hash(), SerializableDateTime::some()));

        $this->tokenRepository->revoke();

        $this->assertNull($this->tokenRepository->find());
    }

    public function testFindThrowsOnACorruptedHash(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::API_TOKEN,
            Value::fromString(Json::encode(['hash' => '', 'createdOn' => '2026-08-21T09:12:00+00:00'])),
        ));

        $this->expectExceptionObject(new \InvalidArgumentException('Invalid API token hash'));

        $this->tokenRepository->find();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $this->tokenRepository = new KeyValueBasedTokenRepository($this->keyValueStore);
    }
}
