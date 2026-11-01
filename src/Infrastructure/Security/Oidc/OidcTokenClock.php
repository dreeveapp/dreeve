<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Oidc;

use Psr\Clock\ClockInterface;

final readonly class OidcTokenClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('+60 seconds');
    }
}
