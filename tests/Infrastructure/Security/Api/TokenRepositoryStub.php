<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Api;

use App\Domain\Api\StoredToken;
use App\Domain\Api\TokenRepository;

final class TokenRepositoryStub implements TokenRepository
{
    private function __construct(
        private ?StoredToken $storedToken,
    ) {
    }

    public static function empty(): self
    {
        return new self(null);
    }

    public static function holding(StoredToken $storedToken): self
    {
        return new self($storedToken);
    }

    public function find(): ?StoredToken
    {
        return $this->storedToken;
    }

    public function save(StoredToken $token): void
    {
        $this->storedToken = $token;
    }

    public function revoke(): void
    {
        $this->storedToken = null;
    }
}
