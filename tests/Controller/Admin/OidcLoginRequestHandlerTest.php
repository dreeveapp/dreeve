<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\OidcLoginRequestHandler;
use App\Infrastructure\Security\Oidc\OidcConfig;
use App\Infrastructure\Security\Oidc\OidcLoginAttempt;
use App\Infrastructure\Security\Oidc\OidcProvider;
use App\Infrastructure\Serialization\Json;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OidcLoginRequestHandlerTest extends AdminWebTestCase
{
    private const string ISSUER_URL = 'https://idp.example.com/application/o/dreeve/';
    private const string TOKEN_ENDPOINT = 'https://idp.example.com/application/o/token/';
    private const string JWKS_URI = 'https://idp.example.com/application/o/dreeve/jwks/';

    private JWK $signingKey;
    private string $idToken = '';
    /** @var array<string, string> */
    private array $discoveryDocument = [];
    /** @var array<int, array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    #[\Override]
    protected function prepareEnvironment(): void
    {
        parent::prepareEnvironment();

        $_SERVER['OIDC_ISSUER_URL'] = $_ENV['OIDC_ISSUER_URL'] = self::ISSUER_URL;
        $_SERVER['OIDC_CLIENT_ID'] = $_ENV['OIDC_CLIENT_ID'] = 'dreeve';
        $_SERVER['OIDC_CLIENT_SECRET'] = $_ENV['OIDC_CLIENT_SECRET'] = 'secret';
        $_SERVER['OIDC_ALLOWED_USERS'] = $_ENV['OIDC_ALLOWED_USERS'] = 'athlete-subject, athlete@example.com';
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->signingKey = JWKFactory::createECKey('P-256', ['alg' => 'ES256', 'use' => 'sig', 'kid' => 'dreeve-test']);
        $this->discoveryDocument = [
            'issuer' => self::ISSUER_URL,
            'authorization_endpoint' => 'https://idp.example.com/application/o/authorize/',
            'token_endpoint' => self::TOKEN_ENDPOINT,
            'jwks_uri' => self::JWKS_URI,
        ];

        static::getContainer()->set(OidcProvider::class, new OidcProvider(
            static::getContainer()->get(OidcConfig::class),
            new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
                $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

                return match ($url) {
                    self::ISSUER_URL.'.well-known/openid-configuration' => new JsonMockResponse($this->discoveryDocument),
                    self::JWKS_URI => new JsonMockResponse(['keys' => [$this->signingKey->toPublic()->all()]]),
                    self::TOKEN_ENDPOINT => new JsonMockResponse(['access_token' => 'access-token', 'token_type' => 'Bearer', 'id_token' => $this->idToken]),
                    default => throw new \LogicException(sprintf('Unexpected request to "%s"', $url)),
                };
            }),
            new ArrayAdapter(),
            static::getContainer()->get(UrlGeneratorInterface::class),
        ));
    }

    public function testLoginRedirectsToTheProvider(): void
    {
        $this->client->request('GET', '/admin/login/oidc');
        $this->client->request('GET', '/admin/login/oidc');

        $this->assertSame(
            [self::ISSUER_URL.'.well-known/openid-configuration'],
            array_column($this->requests, 'url'),
        );

        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith('https://idp.example.com/application/o/authorize/?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $loginAttempt = $this->client->getRequest()->getSession()->get(OidcLoginAttempt::SESSION_KEY);
        $this->assertInstanceOf(OidcLoginAttempt::class, $loginAttempt);

        $this->assertSame([
            'response_type' => 'code',
            'client_id' => 'dreeve',
            'redirect_uri' => 'http://localhost/admin/login/oidc/callback',
            'scope' => 'openid email profile',
            'state' => $loginAttempt->getState(),
            'nonce' => $loginAttempt->getNonce(),
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $loginAttempt->getCodeVerifier(), true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ], $query);
    }

    /**
     * @param array<string, mixed> $claims
     */
    #[DataProvider(methodName: 'provideAllowedClaims')]
    public function testCallbackLogsInTheAdmin(array $claims): void
    {
        $crawler = $this->client->request('GET', '/admin/login');
        $this->client->click($crawler->filter('a[href$="/admin/login/oidc"]')->link());
        /** @var OidcLoginAttempt $loginAttempt */
        $loginAttempt = $this->client->getRequest()->getSession()->get(OidcLoginAttempt::SESSION_KEY);

        $this->idToken = $this->signIdToken([...$this->validClaims($loginAttempt), ...$claims], $this->signingKey);

        $this->client->request('GET', '/admin/login/oidc/callback', ['code' => 'the-code', 'state' => $loginAttempt->getState()]);
        $this->assertResponseRedirects('/admin/upload');
        $this->assertNotNull($this->client->getCookieJar()->get('REMEMBERME'));

        $tokenRequest = array_first(array_filter($this->requests, fn (array $request): bool => self::TOKEN_ENDPOINT === $request['url']));
        $this->assertSame('POST', $tokenRequest['method']);
        $this->assertContains('Authorization: Basic '.base64_encode('dreeve:secret'), $tokenRequest['options']['normalized_headers']['authorization']);
        parse_str($tokenRequest['options']['body'], $tokenRequestBody);
        $this->assertSame([
            'grant_type' => 'authorization_code',
            'code' => 'the-code',
            'redirect_uri' => 'http://localhost/admin/login/oidc/callback',
            'code_verifier' => $loginAttempt->getCodeVerifier(),
        ], $tokenRequestBody);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function provideAllowedClaims(): iterable
    {
        yield 'allowed subject' => [['sub' => 'athlete-subject']];
        yield 'allowed verified email' => [['sub' => 'unknown', 'email' => 'Athlete@Example.com', 'email_verified' => true]];
    }

    /**
     * @param array<string, mixed> $claims
     */
    #[DataProvider(methodName: 'provideRejectedClaims')]
    public function testCallbackRejectsTheClaims(array $claims): void
    {
        $this->client->request('GET', '/admin/login/oidc');
        /** @var OidcLoginAttempt $loginAttempt */
        $loginAttempt = $this->client->getRequest()->getSession()->get(OidcLoginAttempt::SESSION_KEY);

        $this->idToken = $this->signIdToken(
            array_filter([...$this->validClaims($loginAttempt), ...$claims], fn (mixed $value): bool => null !== $value),
            $this->signingKey,
        );

        $this->client->request('GET', '/admin/login/oidc/callback', ['code' => 'the-code', 'state' => $loginAttempt->getState()]);
        $this->assertLoginIsRejected();
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function provideRejectedClaims(): iterable
    {
        yield 'wrong nonce' => [['nonce' => 'wrong']];
        yield 'missing nonce' => [['nonce' => null]];
        yield 'wrong audience' => [['aud' => 'another-client']];
        yield 'wrong issuer' => [['iss' => 'https://evil.example.com/']];
        yield 'expired' => [['exp' => time() - 60]];
        yield 'subject not allowed' => [['sub' => 'someone-else']];
        yield 'unverified email' => [['sub' => 'someone-else', 'email' => 'athlete@example.com', 'email_verified' => false]];
    }

    public function testCallbackRejectsATamperedState(): void
    {
        $this->client->request('GET', '/admin/login/oidc');
        /** @var OidcLoginAttempt $loginAttempt */
        $loginAttempt = $this->client->getRequest()->getSession()->get(OidcLoginAttempt::SESSION_KEY);

        $this->idToken = $this->signIdToken($this->validClaims($loginAttempt), $this->signingKey);

        $this->client->request('GET', '/admin/login/oidc/callback', ['code' => 'the-code', 'state' => 'tampered']);
        $this->assertLoginIsRejected();
    }

    public function testCallbackRejectsAForeignSigningKey(): void
    {
        $this->client->request('GET', '/admin/login/oidc');
        /** @var OidcLoginAttempt $loginAttempt */
        $loginAttempt = $this->client->getRequest()->getSession()->get(OidcLoginAttempt::SESSION_KEY);

        $this->idToken = $this->signIdToken(
            $this->validClaims($loginAttempt),
            JWKFactory::createECKey('P-256', ['alg' => 'ES256', 'use' => 'sig', 'kid' => 'dreeve-test']),
        );

        $this->client->request('GET', '/admin/login/oidc/callback', ['code' => 'the-code', 'state' => $loginAttempt->getState()]);
        $this->assertLoginIsRejected();
    }

    public function testCallbackRejectsATokenResponseWithoutIdToken(): void
    {
        $this->client->request('GET', '/admin/login/oidc');
        /** @var OidcLoginAttempt $loginAttempt */
        $loginAttempt = $this->client->getRequest()->getSession()->get(OidcLoginAttempt::SESSION_KEY);

        $this->client->request('GET', '/admin/login/oidc/callback', ['code' => 'the-code', 'state' => $loginAttempt->getState()]);
        $this->assertLoginIsRejected();
    }

    public function testCallbackRejectsADiscoveryDocumentWithoutTokenEndpoint(): void
    {
        unset($this->discoveryDocument['token_endpoint']);

        $this->client->request('GET', '/admin/login/oidc');
        /** @var OidcLoginAttempt $loginAttempt */
        $loginAttempt = $this->client->getRequest()->getSession()->get(OidcLoginAttempt::SESSION_KEY);

        $this->client->request('GET', '/admin/login/oidc/callback', ['code' => 'the-code', 'state' => $loginAttempt->getState()]);
        $this->assertLoginIsRejected();
        $this->assertNotContains(self::TOKEN_ENDPOINT, array_column($this->requests, 'url'));
    }

    public function testLoginIsNotFoundWhenOidcIsNotConfigured(): void
    {
        $oidcConfig = OidcConfig::fromStrings('', '', '', '');
        $handler = new OidcLoginRequestHandler(
            $oidcConfig,
            new OidcProvider($oidcConfig, new MockHttpClient(), new ArrayAdapter(), $this->createStub(UrlGeneratorInterface::class)),
        );

        $this->expectException(NotFoundHttpException::class);

        $handler->login(new Request());
    }

    public function testCallbackIsNotFoundWithoutTheAuthenticator(): void
    {
        $oidcConfig = OidcConfig::fromStrings('', '', '', '');
        $handler = new OidcLoginRequestHandler(
            $oidcConfig,
            new OidcProvider($oidcConfig, new MockHttpClient(), new ArrayAdapter(), $this->createStub(UrlGeneratorInterface::class)),
        );

        $this->expectException(NotFoundHttpException::class);

        $handler->callback();
    }

    private function assertLoginIsRejected(): void
    {
        $this->assertResponseRedirects('/admin/login');

        $crawler = $this->client->followRedirect();
        $this->assertCount(1, $crawler->filter('.text-red-700'));

        $this->client->request('GET', '/admin/upload');
        $this->assertResponseRedirects('/admin/login');
    }

    /**
     * @return array<string, mixed>
     */
    private function validClaims(OidcLoginAttempt $loginAttempt): array
    {
        return [
            'iss' => self::ISSUER_URL,
            'sub' => 'athlete-subject',
            'aud' => 'dreeve',
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => $loginAttempt->getNonce(),
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function signIdToken(array $claims, JWK $key): string
    {
        $jws = new JWSBuilder(new AlgorithmManager([new ES256()]))
            ->create()
            ->withPayload(Json::encode($claims))
            ->addSignature($key, ['alg' => 'ES256', 'kid' => 'dreeve-test'])
            ->build();

        return new CompactSerializer()->serialize($jws, 0);
    }
}
