<?php

namespace App\Tests\Controller\Strava;

use App\Controller\Strava\StravaOAuthRequestHandler;
use App\Domain\Import\ImportMode;
use App\Domain\Strava\InsufficientStravaAccessTokenScopes;
use App\Domain\Strava\InvalidStravaAccessToken;
use App\Domain\Strava\Strava;
use App\Domain\Strava\StravaApplicationIsInactive;
use App\Domain\Strava\StravaClientId;
use App\Domain\Strava\StravaClientSecret;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

class StravaOAuthRequestHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private StravaOAuthRequestHandler $stravaOAuthRequestHandler;
    private MockObject $strava;
    private MockObject $client;

    public function testHandleWithValidRefreshToken(): void
    {
        $this->strava
            ->expects($this->once())
            ->method('verifyAccessToken');

        $this->client
            ->expects($this->never())
            ->method('post');

        $response = $this->stravaOAuthRequestHandler->handle(new Request(query: ['code' => 'the-code']));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/', $response->getTargetUrl());
        $this->assertSame(\Symfony\Component\HttpFoundation\Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testHandleWithCode(): void
    {
        $this->strava
            ->expects($this->once())
            ->method('verifyAccessToken')
            ->willThrowException(new InvalidStravaAccessToken());

        $this->client
            ->expects($this->once())
            ->method('post')
            ->with('https://www.strava.com/oauth/token', [
                RequestOptions::FORM_PARAMS => [
                    'grant_type' => 'authorization_code',
                    'client_id' => 'client',
                    'client_secret' => 'secret',
                    'code' => 'the-code',
                ],
            ])
            ->willReturn(new Response(200, [], Json::encode(['refresh_token' => 'the-token'])));

        $this->assertMatchesHtmlSnapshot($this->stravaOAuthRequestHandler->handle(new Request(query: ['code' => 'the-code']))->getContent());
    }

    public function testHandleWithCodeButAnError(): void
    {
        $this->strava
            ->expects($this->once())
            ->method('verifyAccessToken')
            ->willThrowException(new InvalidStravaAccessToken());

        $this->client
            ->expects($this->once())
            ->method('post')
            ->willThrowException(new RequestException(
                message: 'The error',
                request: new \GuzzleHttp\Psr7\Request('GET', 'uri'),
                response: new Response(404, [], Json::encode(['error' => 'The error'])),
            ));

        $page = new Crawler((string) $this->stravaOAuthRequestHandler->handle(new Request(query: ['code' => 'the-code']))->getContent());

        $this->assertSame('Connect your Strava account', $page->filter('h2')->text());
        $this->assertSame('{"error":"The error"}', $page->filter('.text-red-800')->text());
    }

    public function testHandleItShouldStartAuthorization(): void
    {
        $this->strava
            ->expects($this->once())
            ->method('verifyAccessToken')
            ->willThrowException(new InvalidStravaAccessToken());

        $this->client
            ->expects($this->never())
            ->method('post');

        $this->assertMatchesHtmlSnapshot($this->stravaOAuthRequestHandler->handle(new Request())->getContent());
    }

    #[DataProvider('provideVerificationErrors')]
    public function testHandleWhenTheAccessTokenCannotBeVerified(\Throwable $exception, string $expectedTitle, ?string $expectedError): void
    {
        $this->strava
            ->expects($this->once())
            ->method('verifyAccessToken')
            ->willThrowException($exception);

        $this->client
            ->expects($this->never())
            ->method('post');

        $page = new Crawler((string) $this->stravaOAuthRequestHandler->handle(new Request())->getContent());

        $this->assertSame($expectedTitle, $page->filter('h2')->text());
        if (null === $expectedError) {
            $this->assertCount(0, $page->filter('.text-red-800'));

            return;
        }
        $this->assertSame($expectedError, $page->filter('.text-red-800')->text());
    }

    public static function provideVerificationErrors(): iterable
    {
        yield 'insufficient scopes' => [new InsufficientStravaAccessTokenScopes(), 'Your refresh token is missing a scope', null];
        yield 'inactive application' => [
            StravaApplicationIsInactive::create(),
            'Hmmm, something went wrong',
            'Your Strava API application is inactive, so Dreeve can no longer access the Strava API. Reactivate it on https://www.strava.com/settings/api, or switch to IMPORT_MODE=files',
        ];
        yield 'random error' => [new \RuntimeException('OH NOWZ'), 'Hmmm, something went wrong', 'OH NOWZ'];
    }

    public function testHandleItShouldWhenImportModeIsFiles(): void
    {
        $stravaOAuthRequestHandler = new StravaOAuthRequestHandler(
            StravaClientId::fromString('client'),
            StravaClientSecret::fromString('secret'),
            $this->strava,
            $this->client,
            ImportMode::FILES,
            $this->getContainer()->get(Environment::class),
        );

        $this->strava
            ->expects($this->never())
            ->method('verifyAccessToken');

        $this->client
            ->expects($this->never())
            ->method('post');

        $page = new Crawler((string) $stravaOAuthRequestHandler->handle(new Request())->getContent());

        $this->assertSame('Strava authorization is not needed here', $page->filter('h2')->text());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->stravaOAuthRequestHandler = new StravaOAuthRequestHandler(
            StravaClientId::fromString('client'),
            StravaClientSecret::fromString('secret'),
            $this->strava = $this->createMock(Strava::class),
            $this->client = $this->createMock(Client::class),
            ImportMode::STRAVA_API,
            $this->getContainer()->get(Environment::class),
        );
    }
}
