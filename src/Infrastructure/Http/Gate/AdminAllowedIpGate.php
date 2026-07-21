<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Gate;

use App\Infrastructure\Config\AdminAllowedIpAddresses;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Restricts the two ways of reaching configuration — the admin panel and the
 * configuration API — to ADMIN_ALLOWED_IPS, so one setting covers both.
 */
#[AsTaggedItem(priority: 100)]
final readonly class AdminAllowedIpGate implements Gate
{
    public function __construct(
        private AdminAllowedIpAddresses $allowedIps,
    ) {
    }

    public function handle(Request $request): GateDecision
    {
        if ($this->allowedIps->isEmpty()) {
            return GateDecision::defer();
        }

        if (!$this->guardsPath($request->getPathInfo())) {
            return GateDecision::defer();
        }

        $clientIp = $request->headers->get('CF-Connecting-IP') ?? $request->getClientIp();
        if ($this->allowedIps->contains($clientIp)) {
            // Only the IP check passed, the other gates still get to decide.
            return GateDecision::defer();
        }

        throw new NotFoundHttpException('Not found');
    }

    private function guardsPath(string $path): bool
    {
        return '/admin' === $path
            || str_starts_with($path, '/admin/')
            || str_starts_with($path, self::API_PATH_PREFIX);
    }
}
