<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Oidc;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\ES512;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\PS384;
use Jose\Component\Signature\Algorithm\PS512;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS384;
use Jose\Component\Signature\Algorithm\RS512;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcTokenHandler;
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

    /**
     * @return array<string, mixed>
     */
    public function fetchIdTokenClaims(string $code, OidcLoginAttempt $loginAttempt): array
    {
        $tokens = $this->httpClient->request('POST', $this->getDiscoveredString('token_endpoint'), [
            'auth_basic' => [rawurlencode($this->oidcConfig->getClientId()), rawurlencode($this->oidcConfig->getClientSecret())],
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->getRedirectUri(),
                'code_verifier' => $loginAttempt->getCodeVerifier(),
            ],
        ])->toArray();

        $idToken = $tokens['id_token'] ?? null;
        if (!is_string($idToken) || '' === $idToken) {
            throw new \RuntimeException('The OIDC token response has no "id_token"');
        }

        $issuerUrl = (string) $this->oidcConfig->getIssuerUrl();
        $idTokenHandler = new OidcTokenHandler(
            signatureAlgorithm: new AlgorithmManager([
                new RS256(), new RS384(), new RS512(),
                new PS256(), new PS384(), new PS512(),
                new ES256(), new ES384(), new ES512(),
            ]),
            signatureKeyset: null,
            audience: $this->oidcConfig->getClientId(),
            issuers: [$this->getDiscoveredString('issuer')],
        );
        $idTokenHandler->enableDiscovery(
            cache: $this->cache,
            client: $this->httpClient->withOptions(['base_uri' => rtrim($issuerUrl, '/').'/']),
            oidcConfigurationCacheKey: 'oidc_jwks_'.hash('xxh128', $issuerUrl),
            enforceKeyUsageVerification: false,
        );

        $claims = $idTokenHandler->getUserBadgeFrom($idToken)->getAttributes() ?? [];

        $nonce = $claims['nonce'] ?? null;
        if (!is_string($nonce) || !hash_equals($loginAttempt->getNonce(), $nonce)) {
            throw new \RuntimeException('The OIDC id token has an invalid nonce');
        }

        return $claims;
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
