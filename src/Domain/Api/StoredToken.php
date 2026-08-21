<?php

declare(strict_types=1);

namespace App\Domain\Api;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class StoredToken implements \JsonSerializable
{
    private function __construct(
        private TokenHash $hash,
        private SerializableDateTime $createdOn,
    ) {
    }

    public static function create(TokenHash $hash, SerializableDateTime $createdOn): self
    {
        return new self($hash, $createdOn);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!is_string($data['hash'] ?? null) || !is_string($data['createdOn'] ?? null)) {
            throw new \InvalidArgumentException('Invalid API token state');
        }

        return new self(
            TokenHash::fromString($data['hash']),
            SerializableDateTime::fromString($data['createdOn']),
        );
    }

    public function getHash(): TokenHash
    {
        return $this->hash;
    }

    public function getCreatedOn(): SerializableDateTime
    {
        return $this->createdOn;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'hash' => (string) $this->hash,
            'createdOn' => $this->createdOn->iso(),
        ];
    }
}
