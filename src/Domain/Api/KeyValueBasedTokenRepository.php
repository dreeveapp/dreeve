<?php

declare(strict_types=1);

namespace App\Domain\Api;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;

final readonly class KeyValueBasedTokenRepository implements TokenRepository
{
    public function __construct(
        private KeyValueStore $keyValueStore,
    ) {
    }

    public function find(): ?StoredToken
    {
        try {
            $value = $this->keyValueStore->find(Key::API_TOKEN);
        } catch (EntityNotFound) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = Json::decode((string) $value);

        return StoredToken::fromArray($data);
    }

    public function save(StoredToken $token): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::API_TOKEN,
            Value::fromString(Json::encode($token)),
        ));
    }

    public function revoke(): void
    {
        $this->keyValueStore->clear(Key::API_TOKEN);
    }
}
