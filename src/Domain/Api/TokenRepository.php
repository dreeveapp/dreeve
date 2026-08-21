<?php

declare(strict_types=1);

namespace App\Domain\Api;

interface TokenRepository
{
    public function find(): ?StoredToken;

    public function save(StoredToken $token): void;

    public function revoke(): void;
}
