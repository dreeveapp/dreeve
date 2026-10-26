<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\OidcLoginRequestHandler;
use App\Infrastructure\Security\Oidc\OidcConfig;
use App\Infrastructure\Security\Oidc\OidcLoginAttempt;
use App\Infrastructure\Security\Oidc\OidcProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OidcLoginRequestHandlerTest extends AdminWebTestCase
{
    private const string ISSUER_URL = 'https://idp.example.com/application/o/dreeve/';

    /** @var string[] */
    private array $requestedUrls = [];

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

        static::getContainer()->set(OidcProvider::class, new OidcProvider(
            static::getContainer()->get(OidcConfig::class),
            new MockHttpClient(function (string $method, string $url): JsonMockResponse {
                $this->requestedUrls[] = $url;

                return match ($url) {
                    self::ISSUER_URL.'.well-known/openid-configuration' => new JsonMockResponse([
                        'issuer' => self::ISSUER_URL,
                        'authorization_endpoint' => 'https://idp.example.com/application/o/authorize/',
                    ]),
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

        $this->assertSame([self::ISSUER_URL.'.well-known/openid-configuration'], $this->requestedUrls);

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
}
