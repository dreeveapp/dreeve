<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;

final readonly class ClientIpResolver
{
    public function resolve(Request $request): ?string
    {
        if (!$request->isFromTrustedProxy()) {
            return $request->getClientIp();
        }

        // Cloudflare puts the original client here, while X-Forwarded-For may carry extra hops behind it.
        return $request->headers->get('CF-Connecting-IP') ?? $request->getClientIp();
    }
}
