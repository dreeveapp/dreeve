<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Oidc;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OidcProvider
{
    public function __construct(
        private OidcConfig $oidcConfig,
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildAuthorizationUrl(OidcLoginAttempt $loginAttempt): string
    {
        $authorizationEndpoint = $this->getDiscoveredString('authorization_endpoint');

        return $authorizationEndpoint.(str_contains($authorizationEndpoint, '?') ? '&' : '?').http_build_query([
            'response_type' => 'code',
            'client_id' => $this->oidcConfig->getClientId(),
            'redirect_uri' => $this->getRedirectUri(),
            'scope' => 'openid email profile',
            'state' => $loginAttempt->getState(),
            'nonce' => $loginAttempt->getNonce(),
            'code_challenge' => $loginAttempt->getCodeChallenge(),
            'code_challenge_method' => 'S256',
        ], encoding_type: PHP_QUERY_RFC3986);
    }

    private function getRedirectUri(): string
    {
        return $this->urlGenerator->generate('admin_login_oidc_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function getDiscoveredString(string $name): string
    {
        $value = $this->getDiscoveryDocument()[$name] ?? null;
        if (!is_string($value) || '' === $value) {
            throw new \RuntimeException(sprintf('The OIDC discovery document has no "%s"', $name));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDiscoveryDocument(): array
    {
        $issuerUrl = (string) $this->oidcConfig->getIssuerUrl();

        return $this->cache->get('oidc_discovery_'.hash('xxh128', $issuerUrl), function (ItemInterface $item) use ($issuerUrl): array {
            $item->expiresAfter(3600);

            return $this->httpClient
                ->request('GET', rtrim($issuerUrl, '/').'/.well-known/openid-configuration')
                ->toArray();
        });
    }
}
