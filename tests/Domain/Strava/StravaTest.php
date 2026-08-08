<?php

namespace App\Tests\Domain\Strava;

use App\Application\Import\StravaImport\ImportChallenges\ImportChallengesCommandHandler;
use App\Domain\Activity\ActivityId;
use App\Domain\Gear\GearId;
use App\Domain\Segment\SegmentId;
use App\Domain\Strava\InsufficientStravaAccessTokenScopes;
use App\Domain\Strava\InvalidStravaAccessToken;
use App\Domain\Strava\RateLimit\StravaRateLimitHasBeenReached;
use App\Domain\Strava\Strava;
use App\Domain\Strava\StravaClientId;
use App\Domain\Strava\StravaClientSecret;
use App\Domain\Strava\StravaRefreshToken;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\Url;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use App\Tests\Infrastructure\Time\Sleep\NullSleep;
use App\Tests\SpyOutput;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Spatie\Snapshots\MatchesSnapshots;

class StravaTest extends TestCase
{
    use MatchesSnapshots;

    private Strava $strava;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&\GuzzleHttp\Client
     */
    private MockObject $client;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&\League\Flysystem\FilesystemOperator
     */
    private MockObject $filesystemOperator;
    private NullSleep $sleep;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&\Psr\Log\LoggerInterface
     */
    private MockObject $logger;

    public function testGetAccessToken(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->logger
            ->expects($this->never())
            ->method('log');

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'oauth/token',
            )
        ->willReturn(new Response(200, [], Json::encode(['access_token' => 'theAccessToken'])));

        $this->strava->getAccessToken();
        $this->strava->getAccessToken();
    }

    public function testVerifyAccessToken(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->logger
            ->expects($this->never())
            ->method('log');

        $matcher = $this->exactly(2);
        $this->client
            ->expects($matcher)
            ->method('request')
            ->willReturnCallback(function (string $method, string $path, array $options) use ($matcher): Response {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertEquals('POST', $method);
                    $this->assertEquals('oauth/token', $path);
                    $this->assertMatchesJsonSnapshot($options);

                    return new Response(200, [], Json::encode(['access_token' => 'theAccessToken']));
                }

                $this->assertEquals('GET', $method);
                $this->assertEquals('api/v3/athlete/activities', $path);
                $this->assertMatchesJsonSnapshot($options);

                return new Response(200, [], Json::encode([]));
            });

        $this->strava->verifyAccessToken();
    }

    public function testVerifyAccessTokenWhenTheTokenIsInvalid(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->logger
            ->expects($this->never())
            ->method('log');

        $this->client
            ->expects($this->once())
            ->method('request')
            ->willThrowException(RequestException::wrapException(
                new Request('GET', 'uri'),
                new \RuntimeException()
            ));

        $this->expectExceptionObject(new InvalidStravaAccessToken());

        $this->strava->verifyAccessToken();
    }

    public function testVerifyAccessTokenWhenTheTokenHasInsufficientScopes(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->logger
            ->expects($this->never())
            ->method('log');

        $matcher = $this->exactly(2);
        $this->client
            ->expects($matcher)
            ->method('request')
            ->willReturnCallback(function (string $method, string $path) use ($matcher): Response {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertEquals('POST', $method);
                    $this->assertEquals('oauth/token', $path);

                    return new Response(200, [], Json::encode(['access_token' => 'theAccessToken']));
                }

                $this->assertEquals('GET', $method);
                $this->assertEquals('api/v3/athlete/activities', $path);

                throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(401, [], Json::encode(['error' => 'The error'])));
            });

        $this->expectExceptionObject(new InsufficientStravaAccessTokenScopes());

        $this->strava->verifyAccessToken();
    }

    public function testVerifyAccessTokenWhenARandomErrorIsThrown(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->logger
            ->expects($this->never())
            ->method('log');

        $matcher = $this->exactly(2);
        $this->client
            ->expects($matcher)
            ->method('request')
            ->willReturnCallback(function (string $method, string $path) use ($matcher): Response {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertEquals('POST', $method);
                    $this->assertEquals('oauth/token', $path);

                    return new Response(200, [], Json::encode(['access_token' => 'theAccessToken']));
                }

                $this->assertEquals('GET', $method);
                $this->assertEquals('api/v3/athlete/activities', $path);

                throw new \RuntimeException('Oh no');
            });

        $this->expectExceptionObject(new \RuntimeException('Oh no'));

        $this->strava->verifyAccessToken();
    }

    public function testGetWhenUnexpectedError(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessageIsOrContains('The error');

        $matcher = $this->exactly(2);
        $this->client
            ->expects($matcher)
            ->method('request')
            ->willReturnCallback(function (string $method, string $path) use ($matcher): Response {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertEquals('POST', $method);
                    $this->assertEquals('oauth/token', $path);

                    return new Response(200, [], Json::encode(['access_token' => 'theAccessToken']));
                }

                throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(500, [], Json::encode(['error' => 'The error'])));
            });

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->strava->getAthlete();
    }

    public function testGetWhenUnexpectedErrorWithoutBody(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessageIsOrContains('The error');

        $matcher = $this->exactly(2);
        $this->client
            ->expects($matcher)
            ->method('request')
            ->willReturnCallback(function (string $method, string $path) use ($matcher): Response {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertEquals('POST', $method);
                    $this->assertEquals('oauth/token', $path);

                    return new Response(200, [], Json::encode(['access_token' => 'theAccessToken']));
                }

                throw new RequestException(message: 'The error', request: new Request('GET', 'uri'));
            });

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->strava->getAthlete();
    }

    public function testGetWhenTooManyRequestsButNoRateLimits(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessageIsOrContains('The error');

        $matcher = $this->exactly(2);
        $this->client
            ->expects($matcher)
            ->method('request')
            ->willReturnCallback(function (string $method, string $path) use ($matcher): Response {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertEquals('POST', $method);
                    $this->assertEquals('oauth/token', $path);

                    return new Response(200, [], Json::encode(['access_token' => 'theAccessToken']));
                }

                throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(429, [], Json::encode(['error' => 'The error'])));
            });

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->strava->getAthlete();
    }

    public function testGetWhenTooManyRequestsDailyRateLimitExceeded(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->expectExceptionObject(StravaRateLimitHasBeenReached::dailyReadLimit());

        $matcher = $this->exactly(2);
        $this->client
            ->expects($matcher)
            ->method('request')
            ->willReturnCallback(function (string $method, string $path) use ($matcher): Response {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertEquals('POST', $method);
                    $this->assertEquals('oauth/token', $path);

                    return new Response(200, [], Json::encode(['access_token' => 'theAccessToken']));
                }

                throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(429, ['x-ratelimit-limit' => '200,2000', 'x-ratelimit-usage' => '1,2', 'x-readratelimit-limit' => '100,1000', 'x-readratelimit-usage' => '99,1001'], Json::encode(['error' => 'The error'])));
            });

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->strava->getAthlete();
    }

    public function testGetWhenTooManyRequestsFifteenMinuteRateLimitExceeded(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->expectExceptionObject(StravaRateLimitHasBeenReached::fifteenMinuteReadLimit(3));

        $matcher = $this->exactly(2);
        $this->client
            ->expects($matcher)
            ->method('request')
            ->willReturnCallback(function (string $method, string $path) use ($matcher): Response {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertEquals('POST', $method);
                    $this->assertEquals('oauth/token', $path);

                    return new Response(200, [], Json::encode(['access_token' => 'theAccessToken']));
                }

                throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(429, ['x-ratelimit-limit' => '200,2000', 'x-ratelimit-usage' => '1,2', 'x-readratelimit-limit' => '100,1000', 'x-readratelimit-usage' => '101,998'], Json::encode(['error' => 'The error'])));
            });

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->strava->getAthlete();
    }

    public function testGetFifteenRateLimitIsAboutToBeHit(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $matcher = $this->exactly(2);
        $this->client
            ->expects($matcher)
            ->method('request')
            ->willReturnCallback(function (string $method, string $path) use ($matcher): Response {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertEquals('POST', $method);
                    $this->assertEquals('oauth/token', $path);

                    return new Response(200, [], Json::encode(['access_token' => 'theAccessToken']));
                }

                return new Response(200, [
                    'x-ratelimit-limit' => '200,2000',
                    'x-ratelimit-usage' => '1,2',
                    'x-readratelimit-limit' => '100,1000',
                    'x-readratelimit-usage' => '99,98',
                ], Json::encode(['weight' => 68, 'id' => 10]));
            });

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $spyOutput = new SpyOutput();
        $this->strava->setConsoleOutput($spyOutput);
        $this->strava->getAthlete();

        $this->assertMatchesTextSnapshot((string) $spyOutput);

        $this->assertEquals(
            180,
            $this->sleep->getTotalSleptInSeconds(),
        );
    }

    public function testGetAthlete(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $matcher = $this->exactly(2);
        $this->client
            ->expects($matcher)
            ->method('request')
            ->willReturnCallback(function (string $method, string $path, array $options) use ($matcher): Response {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertEquals('POST', $method);
                    $this->assertEquals('oauth/token', $path);
                    $this->assertMatchesJsonSnapshot($options);

                    return new Response(200, [], Json::encode(['access_token' => 'theAccessToken']));
                }

                $this->assertEquals('GET', $method);
                $this->assertEquals('api/v3/athlete', $path);
                $this->assertMatchesJsonSnapshot($options);

                return new Response(200, [
                    'x-ratelimit-limit' => '200,2000',
                    'x-ratelimit-usage' => '1,2',
                    'x-readratelimit-limit' => '100,1000',
                    'x-readratelimit-usage' => '0,0',
                ], Json::encode(['weight' => 68, 'id' => 10]));
            });

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $this->strava->getAthlete();
        $this->assertMatchesObjectSnapshot($this->strava->getRateLimit());
        $this->assertEquals(
            0,
            $this->sleep->getTotalSleptInSeconds(),
        );
    }

    /**
     * @param \Closure(Strava): mixed $makeApiCall
     */
    #[DataProvider('provideApiEndpointCalls')]
    public function testGetApiEndpoint(\Closure $makeApiCall, string $expectedPath): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $matcher = $this->exactly(2);
        $this->client
            ->expects($matcher)
            ->method('request')
            ->willReturnCallback(function (string $method, string $path, array $options) use ($matcher, $expectedPath): Response {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertEquals('POST', $method);
                    $this->assertEquals('oauth/token', $path);
                    $this->assertMatchesJsonSnapshot($options);

                    return new Response(200, [], Json::encode(['access_token' => 'theAccessToken']));
                }

                $this->assertEquals('GET', $method);
                $this->assertEquals($expectedPath, $path);
                $this->assertMatchesJsonSnapshot($options);

                return new Response(200, [], Json::encode([]));
            });

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $makeApiCall($this->strava);
    }

    /**
     * @return iterable<string, array{\Closure(Strava): mixed, string}>
     */
    public static function provideApiEndpointCalls(): iterable
    {
        yield 'activities' => [
            static function (Strava $strava): void {
                $strava->getActivities();
                // Test static cache.
                $strava->getActivities();
            },
            'api/v3/athlete/activities',
        ];

        yield 'activity' => [
            static fn (Strava $strava) => $strava->getActivity(ActivityId::fromUnprefixed(3)),
            'api/v3/activities/3',
        ];

        yield 'activity zones' => [
            static fn (Strava $strava) => $strava->getActivityZones(ActivityId::fromUnprefixed(3)),
            'api/v3/activities/3/zones',
        ];

        yield 'activity streams' => [
            static fn (Strava $strava) => $strava->getAllActivityStreams(ActivityId::fromUnprefixed(3)),
            'api/v3/activities/3/streams',
        ];

        yield 'activity photos' => [
            static fn (Strava $strava) => $strava->getActivityPhotos(ActivityId::fromUnprefixed(3)),
            'api/v3/activities/3/photos',
        ];

        yield 'gear' => [
            static fn (Strava $strava) => $strava->getGear(GearId::fromUnprefixed(3)),
            'api/v3/gear/3',
        ];

        yield 'segment' => [
            static fn (Strava $strava) => $strava->getSegment(SegmentId::fromUnprefixed(3)),
            'api/v3/segments/3',
        ];
    }

    public function testGetWebhookSubscription(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'api/v3/push_subscriptions',
                [
                    'base_uri' => 'https://www.strava.com/',
                    RequestOptions::QUERY => [
                        'client_id' => 'clientId',
                        'client_secret' => 'clientSecret',
                    ],
                ]
            )
            ->willReturn(new Response(200, [], Json::encode(['id' => 12345])));

        $this->strava->getWebhookSubscription();
    }

    public function testCreateWebhookSubscription(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'api/v3/push_subscriptions',
                [
                    'base_uri' => 'https://www.strava.com/',
                    RequestOptions::FORM_PARAMS => [
                        'client_id' => 'clientId',
                        'client_secret' => 'clientSecret',
                        'callback_url' => 'https://example.com/',
                        'verify_token' => 'the-token',
                    ],
                ]
            )
            ->willReturn(new Response(200, [], Json::encode(['id' => 12345])));

        $this->strava->createWebhookSubscription(
            callbackUrl: Url::fromString('https://example.com/'),
            verifyToken: 'the-token',
        );
    }

    public function testDeleteWebhookSubscription(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with(
                'DELETE',
                'api/v3/push_subscriptions/the-id',
                [
                    'base_uri' => 'https://www.strava.com/',
                    RequestOptions::QUERY => [
                        'client_id' => 'clientId',
                        'client_secret' => 'clientSecret',
                    ],
                ]
            )
            ->willReturn(new Response(200, [], Json::encode(['id' => 12345])));

        $this->strava->deleteWebhookSubscription('the-id');
    }

    public function testGetChallengesOnTrophyCase(): void
    {
        $this->client
            ->expects($this->never())
            ->method('request');

        $this->filesystemOperator
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(true);

        $this->filesystemOperator
            ->expects($this->once())
            ->method('read')
            ->with('storage/files/strava-challenge-history.html')
            ->willReturn(file_get_contents(__DIR__.'/trophy-case.html'));

        $this->logger
            ->expects($this->never())
            ->method('info');

        $challenges = $this->strava->getChallengesOnTrophyCase();
        $this->assertMatchesJsonSnapshot($challenges);
    }

    public function testGetChallengesOnTrophyCaseWhenFileNotFound(): void
    {
        $this->client
            ->expects($this->never())
            ->method('request');

        $this->filesystemOperator
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(false);

        $this->filesystemOperator
            ->expects($this->never())
            ->method('read');

        $this->logger
            ->expects($this->never())
            ->method('info');

        $challenges = $this->strava->getChallengesOnTrophyCase();
        $this->assertEmpty($challenges);
    }

    public function testGetChallengesOnTrophyCaseWithDefaultHtml(): void
    {
        $this->client
            ->expects($this->never())
            ->method('request');

        $this->filesystemOperator
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(true);

        $this->filesystemOperator
            ->expects($this->once())
            ->method('read')
            ->with('storage/files/strava-challenge-history.html')
            ->willReturn(ImportChallengesCommandHandler::DEFAULT_STRAVA_CHALLENGE_HISTORY);

        $this->logger
            ->expects($this->never())
            ->method('info');

        $challenges = $this->strava->getChallengesOnTrophyCase();
        $this->assertEmpty($challenges);
    }

    #[DataProvider('provideInvalidTrophyCaseHtml')]
    public function testGetChallengesOnTrophyCaseItShouldThrow(string $html, string $expectedExceptionMessage): void
    {
        $this->client
            ->expects($this->never())
            ->method('request');

        $this->filesystemOperator
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(true);

        $this->filesystemOperator
            ->expects($this->once())
            ->method('read')
            ->with('storage/files/strava-challenge-history.html')
            ->willReturn($html);

        $this->logger
            ->expects($this->never())
            ->method('info');

        $this->expectExceptionObject(new \RuntimeException($expectedExceptionMessage));

        $this->strava->getChallengesOnTrophyCase();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidTrophyCaseHtml(): iterable
    {
        yield 'empty html' => [
            '',
            'Could not fetch Strava challenges from trophy case',
        ];

        yield 'invalid html' => [
            "<ul class='list-block-grid list-trophies'>YEAHBABY</ul>",
            'Could not fetch Strava challenges from trophy case',
        ];

        yield 'name not found' => [
            (string) file_get_contents(__DIR__.'/trophy-case-without-name.html'),
            'Could not fetch Strava challenge name',
        ];

        yield 'teaser not found' => [
            (string) file_get_contents(__DIR__.'/trophy-case-without-teaser.html'),
            'Could not fetch Strava challenge teaser',
        ];

        yield 'logo not found' => [
            (string) file_get_contents(__DIR__.'/trophy-case-without-logo.html'),
            'Could not fetch Strava challenge logoUrl',
        ];

        yield 'url not found' => [
            (string) file_get_contents(__DIR__.'/trophy-case-without-url.html'),
            'Could not fetch Strava challenge url',
        ];

        yield 'id not found' => [
            (string) file_get_contents(__DIR__.'/trophy-case-without-id.html'),
            'Could not fetch Strava challenge challengeId',
        ];

        yield 'timestamp not found' => [
            (string) file_get_contents(__DIR__.'/trophy-case-without-timestamp.html'),
            'Could not fetch Strava challenge timestamp',
        ];

        yield 'empty timestamp' => [
            (string) file_get_contents(__DIR__.'/trophy-case-with-empty-timestamp.html'),
            'Could not fetch Strava challenge timestamp',
        ];
    }

    public function testDownloadImage(): void
    {
        $this->filesystemOperator
            ->expects($this->never())
            ->method('fileExists');

        $this->client
            ->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $path, array $options): Response {
                $this->assertEquals('GET', $method);
                $this->assertEquals('uri', $path);

                return new Response(200, [], '');
            });

        $this->logger
            ->expects($this->never())
            ->method('info');

        $this->strava->downloadImage('uri');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(Client::class);
        $this->filesystemOperator = $this->createMock(FilesystemOperator::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->strava = new Strava(
            client: $this->client,
            stravaClientId: StravaClientId::fromString('clientId'),
            stravaClientSecret: StravaClientSecret::fromString('clientSecret'),
            stravaRefreshToken: StravaRefreshToken::fromString('refreshToken'),
            filesystemOperator: $this->filesystemOperator,
            sleep: $this->sleep = new NullSleep(),
            logger: $this->logger,
            clock: PausedClock::fromString('2025-11-02 12:43:20')
        );
        $this->strava::$cachedAccessToken = null;
        $this->strava::$cachedActivitiesResponse = null;
    }
}
