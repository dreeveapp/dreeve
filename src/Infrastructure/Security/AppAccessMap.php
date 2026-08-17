<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Settings\SettingsRepository;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessMapInterface;

#[AsDecorator('security.access_map')]
final readonly class AppAccessMap implements AccessMapInterface
{
    /**
     * @var list<string>
     */
    private const array PUBLIC_PATHS = [
        // Called by Strava's servers, they have no session.
        '/strava/webhook',
        // Setup pages that have to be reachable before there is anything to log into.
        '/strava-oauth',
        '/finish-setup',
        '/manifest.json',
        // Caddy serves these.
        '/assets',
        '/css',
        '/js',
        '/images',
        '/libraries',
    ];

    public function __construct(
        #[AutowireDecorated]
        private AccessMapInterface $accessMap,
        private SettingsRepository $settingsRepository,
    ) {
    }

    /**
     * @return array{0: array<string>|null, 1: string|null}
     */
    public function getPatterns(Request $request): array
    {
        [$attributes, $channel] = $this->accessMap->getPatterns($request);
        if (null !== $attributes) {
            // security.yaml has the first say, ^/admin/login stays public.
            return [$attributes, $channel];
        }

        if (!$this->settingsRepository->security()->requiresAuthentication()) {
            return [null, null];
        }

        if ($this->isPublicPath($request->getPathInfo())) {
            return [null, null];
        }

        return [['ROLE_ADMIN'], null];
    }

    private function isPublicPath(string $path): bool
    {
        return array_any(self::PUBLIC_PATHS, fn (string $publicPath): bool => $path === $publicPath || str_starts_with($path, $publicPath.'/'));
    }
}
