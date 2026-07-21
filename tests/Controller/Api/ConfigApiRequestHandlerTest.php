<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Controller\Api\ConfigApiRequestHandler;
use App\Domain\Settings\Api\ConfigResourceRegistry;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ProvideSettings;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ConfigApiRequestHandlerTest extends WebTestCase
{
    use ProvideSettings;

    private const string API_TOKEN = 'a-token-that-is-long-enough-to-be-accepted';

    private KernelBrowser $client;
    /** @var list<string> */
    private array $overriddenEnv = [];

    public function testItRejectsRequestsWithoutAToken(): void
    {
        $this->client->request('GET', '/api/v1/config');

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame(
            'Missing or malformed Authorization header, expected "Authorization: Bearer <token>".',
            $this->responseBody()['message']
        );
    }

    public function testItRejectsAnInvalidToken(): void
    {
        $this->request('GET', '/api/v1/config', token: 'not-the-configured-token-but-long-enough');

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame('Invalid API token.', $this->responseBody()['message']);
    }

    public function testItRejectsEveryTokenWhenTheApiIsDisabled(): void
    {
        $this->rebootWithApiToken('');
        $this->request('GET', '/api/v1/config');

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame('The API is disabled. Set API_TOKEN to enable it.', $this->responseBody()['message']);
    }

    /**
     * The API is exempt from the setup-flow gates in GateRequestListener, so
     * clients get JSON instead of a 302 to a setup page. These two tests pin down
     * that the exemption covers redirects only and does not weaken auth.
     *
     * This test case never marks the app as built, so the gates are live: proven
     * by the sibling test below, where a non-API path does get redirected.
     */
    public function testItServesTheApiEvenWhenTheAppIsNotBuilt(): void
    {
        $this->request('GET', '/api/v1/config');

        $this->assertResponseIsSuccessful();
    }

    public function testANonApiPathIsStillRedirectedByTheGates(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseRedirects();
    }

    public function testBypassingTheGatesDoesNotBypassAuthentication(): void
    {
        $this->client->request('GET', '/api/v1/config/athlete/weight-history');

        $this->assertResponseStatusCodeSame(401);
        $this->assertFalse($this->client->getResponse()->isRedirection());
    }

    public function testTheAdminIpAllowListAlsoRestrictsTheApi(): void
    {
        // ADMIN_ALLOWED_IPS guards both routes into configuration. The test
        // client reports 127.0.0.1, which is not on this list.
        $this->rebootWithEnv(['ADMIN_ALLOWED_IPS' => '192.168.1.1']);
        $this->request('GET', '/api/v1/config');

        // 404 rather than 403, matching how the gate conceals the admin panel.
        $this->assertResponseStatusCodeSame(404);
    }

    public function testItServesTheApiWhenTheClientIpIsAllowed(): void
    {
        $this->rebootWithEnv(['ADMIN_ALLOWED_IPS' => '127.0.0.1']);
        $this->request('GET', '/api/v1/config');

        $this->assertResponseIsSuccessful();
    }

    public function testItListsTheAvailableResources(): void
    {
        $this->request('GET', '/api/v1/config');

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'resources' => [
                ['name' => 'athlete/ftp-history/cycling', 'href' => '/api/v1/config/athlete/ftp-history/cycling', 'methods' => ['GET', 'PUT']],
                ['name' => 'athlete/ftp-history/running', 'href' => '/api/v1/config/athlete/ftp-history/running', 'methods' => ['GET', 'PUT']],
                ['name' => 'athlete/max-heart-rate', 'href' => '/api/v1/config/athlete/max-heart-rate', 'methods' => ['GET', 'PUT']],
                ['name' => 'athlete/resting-heart-rate', 'href' => '/api/v1/config/athlete/resting-heart-rate', 'methods' => ['GET', 'PUT']],
                ['name' => 'athlete/weight-history', 'href' => '/api/v1/config/athlete/weight-history', 'methods' => ['GET', 'PUT']],
                ['name' => 'zwift', 'href' => '/api/v1/config/zwift', 'methods' => ['GET', 'PUT']],
            ],
        ], $this->responseBody());
    }

    public function testItReturns404ForAnUnknownResource(): void
    {
        $this->request('GET', '/api/v1/config/athlete/shoe-size');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('Configuration resource "athlete/shoe-size" does not exist.', $this->responseBody()['message']);
    }

    public function testItReturns404WhenPuttingToAnUnknownResource(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/shoe-size', ['entries' => []]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('Configuration resource "athlete/shoe-size" does not exist.', $this->responseBody()['message']);
    }

    public function testItRejectsANonBearerAuthorizationHeader(): void
    {
        $this->client->request(
            'GET',
            '/api/v1/config',
            server: ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode('admin:password')]
        );

        $this->assertResponseStatusCodeSame(401);
        $this->assertStringContainsString('expected "Authorization: Bearer', $this->responseBody()['message']);
    }

    public function testItReadsALegacyUnkeyedFtpHistoryAsCycling(): void
    {
        // Pre-split histories are a bare list, which FtpHistory reads as
        // cycling. GET must mirror that, and running must come back empty.
        $this->provideLegacyFtpHistory([['on' => '2020-01-01', 'ftp' => 200]]);

        $this->request('GET', '/api/v1/config/athlete/ftp-history/cycling');
        $this->assertSame([
            'sport' => 'cycling',
            'entries' => [['on' => '2020-01-01', 'ftp' => 200]],
        ], $this->responseBody());

        $this->request('GET', '/api/v1/config/athlete/ftp-history/running');
        $this->assertSame(['sport' => 'running', 'entries' => []], $this->responseBody());
    }

    public function testItReadsTheWeightHistory(): void
    {
        $this->request('GET', '/api/v1/config/athlete/weight-history');

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'unit' => 'kg',
            'entries' => [
                ['on' => '2020-01-01', 'weight' => 68],
                ['on' => '2019-12-01', 'weight' => 69],
                ['on' => '2019-08-01', 'weight' => 70],
                ['on' => '2019-07-01', 'weight' => 71],
            ],
        ], $this->responseBody());
    }

    public function testItUpdatesTheWeightHistoryAndEchoesTheStoredState(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/weight-history', [
            'entries' => [
                ['on' => '2024-01-01', 'weight' => 70.5],
                ['on' => '2024-06-01', 'weight' => '69'],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        // assertEquals, not assertSame: a whole-number float round-trips through
        // JSON as an int, so 69.0 comes back as 69.
        $this->assertEquals([
            'unit' => 'kg',
            'entries' => [
                ['on' => '2024-01-01', 'weight' => 70.5],
                ['on' => '2024-06-01', 'weight' => 69.0],
            ],
        ], $this->responseBody());

        $this->assertEquals(
            [['on' => '2024-01-01', 'weight' => 70.5], ['on' => '2024-06-01', 'weight' => 69.0]],
            $this->athleteSettings()['weightHistory']
        );
    }

    public function testItLeavesOtherAthleteSettingsAloneWhenUpdatingWeight(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/weight-history', [
            'entries' => [['on' => '2024-01-01', 'weight' => 70.5]],
        ]);

        $this->assertResponseIsSuccessful();
        $athlete = $this->athleteSettings();
        $this->assertSame('1989-08-14', $athlete['birthday']);
        $this->assertSame('Robin', $athlete['firstName']);
        $this->assertArrayHasKey('ftpHistory', $athlete);
    }

    public function testItRejectsANonNumericWeight(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/weight-history', [
            'entries' => [['on' => '2024-01-01', 'weight' => 'heavy']],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Entry #0 is missing a valid numeric "weight".', $this->responseBody()['message']);
    }

    public function testItRejectsAnInvalidDate(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/weight-history', [
            'entries' => [['on' => 'the-first-of-never', 'weight' => 70]],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('Invalid date', $this->responseBody()['message']);
    }

    public function testItRejectsAUnitThatDoesNotMatchTheConfiguredUnitSystem(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/weight-history', [
            'unit' => 'lb',
            'entries' => [['on' => '2024-01-01', 'weight' => 155]],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('Weights are stored in "kg"', $this->responseBody()['message']);
    }

    public function testItAcceptsAMatchingUnit(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/weight-history', [
            'unit' => 'kg',
            'entries' => [['on' => '2024-01-01', 'weight' => 70]],
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testItRejectsAMalformedBody(): void
    {
        $this->client->request(
            'PUT',
            '/api/v1/config/athlete/weight-history',
            server: $this->authHeaders(self::API_TOKEN),
            content: '{not json'
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testItRejectsANonObjectBody(): void
    {
        $this->client->request(
            'PUT',
            '/api/v1/config/athlete/weight-history',
            server: $this->authHeaders(self::API_TOKEN),
            content: '"a string"'
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Request body must be a JSON object.', $this->responseBody()['message']);
    }

    public function testItReadsTheCyclingFtpHistory(): void
    {
        $this->request('GET', '/api/v1/config/athlete/ftp-history/cycling');

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'sport' => 'cycling',
            'entries' => [
                ['on' => '2023-01-01', 'ftp' => 198],
                ['on' => '2023-03-22', 'ftp' => 220],
                ['on' => '2023-03-29', 'ftp' => 238],
                ['on' => '2023-04-01', 'ftp' => 250],
            ],
        ], $this->responseBody());
    }

    public function testUpdatingOneSportDoesNotClobberTheOther(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/ftp-history/cycling', [
            'entries' => [['on' => '2025-01-01', 'ftp' => 300]],
        ]);

        $this->assertResponseIsSuccessful();

        $ftpHistory = $this->athleteSettings()['ftpHistory'];
        $this->assertSame([['on' => '2025-01-01', 'ftp' => 300]], $ftpHistory['cycling']);
        $this->assertCount(4, $ftpHistory['running']);
    }

    public function testItIgnoresASportInTheBodyAndUsesTheOneFromTheUrl(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/ftp-history/running', [
            'sport' => 'cycling',
            'entries' => [['on' => '2025-01-01', 'ftp' => 150]],
        ]);

        $this->assertResponseIsSuccessful();

        $ftpHistory = $this->athleteSettings()['ftpHistory'];
        $this->assertSame([['on' => '2025-01-01', 'ftp' => 150]], $ftpHistory['running']);
        $this->assertCount(4, $ftpHistory['cycling']);
    }

    public function testItRejectsANonNumericFtp(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/ftp-history/cycling', [
            'entries' => [['on' => '2025-01-01', 'ftp' => 'strong']],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Entry #0 is missing a valid numeric "ftp".', $this->responseBody()['message']);
    }

    public function testItRejectsAnFtpBelowOne(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/ftp-history/cycling', [
            'entries' => [['on' => '2025-01-01', 'ftp' => 0]],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('Minimum FTP of 1 expected', $this->responseBody()['message']);
    }

    public function testItAcceptsAnEmptyHistory(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/ftp-history/cycling', ['entries' => []]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->athleteSettings()['ftpHistory']['cycling']);
    }

    public function testItRejectsEntriesThatAreNotAnArray(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/weight-history', ['entries' => 'nope']);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('"entries" must be an array.', $this->responseBody()['message']);
    }

    public function testAReadOnlyResourceAdvertisesGetOnlyAndRefusesPut(): void
    {
        $handler = new ConfigApiRequestHandler(
            new ConfigResourceRegistry([new StubReadOnlyConfigResource()]),
            $this->getContainer()->get(CommandBus::class),
            $this->getContainer()->get(UrlGeneratorInterface::class),
        );

        $index = Json::decode((string) $handler->index()->getContent());
        $this->assertSame(['GET'], $index['resources'][0]['methods']);

        $response = $handler->update(
            'stub/read-only',
            Request::create('/api/v1/config/stub/read-only', 'PUT', content: '{}')
        );

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET', $response->headers->get('Allow'));
        $this->assertSame(
            'Configuration resource "stub/read-only" is read-only.',
            Json::decode((string) $response->getContent())['message']
        );
    }

    public function testItReadsAMaxHeartRateNamedFormula(): void
    {
        $this->request('GET', '/api/v1/config/athlete/max-heart-rate');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['type' => 'formula', 'formula' => 'fox'], $this->responseBody());
    }

    public function testItSwitchesMaxHeartRateToMeasuredValues(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/max-heart-rate', [
            'type' => 'measured',
            'entries' => [
                ['on' => '2020-01-01', 'bpm' => 198],
                ['on' => '2025-01-10', 'bpm' => 193],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'type' => 'measured',
            'entries' => [
                ['on' => '2020-01-01', 'bpm' => 198],
                ['on' => '2025-01-10', 'bpm' => 193],
            ],
        ], $this->responseBody());
        // Stored in the app's own date => bpm shape, not the API's list shape.
        $this->assertSame(['2020-01-01' => 198, '2025-01-10' => 193], $this->athleteSettings()['maxHeartRateFormula']);
    }

    public function testItRejectsAnUnknownMaxHeartRateFormula(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/max-heart-rate', ['type' => 'formula', 'formula' => 'guesswork']);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('Invalid MAX_HEART_RATE_FORMULA', $this->responseBody()['message']);
    }

    public function testItRejectsADuplicateDateInMeasuredHeartRates(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/max-heart-rate', [
            'type' => 'measured',
            'entries' => [['on' => '2020-01-01', 'bpm' => 198], ['on' => '2020-01-01', 'bpm' => 190]],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('cannot contain the same date more than once', $this->responseBody()['message']);
    }

    public function testItRejectsAnUnknownHeartRateType(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/max-heart-rate', ['type' => 'vibes']);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('A valid "type" is required', $this->responseBody()['message']);
    }

    public function testItDefaultsRestingHeartRateToTheAgeBasedHeuristic(): void
    {
        $this->request('GET', '/api/v1/config/athlete/resting-heart-rate');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['type' => 'formula', 'formula' => 'heuristicAgeBased'], $this->responseBody());
    }

    public function testItSetsAFixedRestingHeartRate(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/resting-heart-rate', ['type' => 'fixed', 'bpm' => 52]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(['type' => 'fixed', 'bpm' => 52], $this->responseBody());
        $this->assertSame(52, $this->athleteSettings()['restingHeartRateFormula']);
    }

    public function testItSetsMeasuredRestingHeartRates(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/resting-heart-rate', [
            'type' => 'measured',
            'entries' => [['on' => '2024-01-01', 'bpm' => 54]],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'type' => 'measured',
            'entries' => [['on' => '2024-01-01', 'bpm' => 54]],
        ], $this->responseBody());
    }

    public function testItRejectsANonPositiveRestingHeartRate(): void
    {
        $this->request('PUT', '/api/v1/config/athlete/resting-heart-rate', ['type' => 'fixed', 'bpm' => 0]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('greater than zero', $this->responseBody()['message']);
    }

    public function testItReadsAndUpdatesZwiftSettings(): void
    {
        $this->request('GET', '/api/v1/config/zwift');
        $this->assertSame(['level' => 80, 'racingScore' => 495], $this->responseBody());

        $this->request('PUT', '/api/v1/config/zwift', ['level' => 42, 'racingScore' => null]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(['level' => 42, 'racingScore' => null], $this->responseBody());
    }

    public function testItRejectsANonNumericZwiftLevel(): void
    {
        $this->request('PUT', '/api/v1/config/zwift', ['level' => 'expert']);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('"level" must be a number or null.', $this->responseBody()['message']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(string $method, string $uri, array $payload = [], string $token = self::API_TOKEN): void
    {
        $this->client->request(
            $method,
            $uri,
            server: $this->authHeaders($token),
            content: 'GET' === $method ? null : Json::encode($payload)
        );
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $token): array
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBody(): array
    {
        return Json::decode((string) $this->client->getResponse()->getContent());
    }

    /**
     * @param list<array{on: string, ftp: int}> $entries
     */
    private function provideLegacyFtpHistory(array $entries): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);

        /** @var KeyValueBasedSettingsRepository $settingsRepository */
        $settingsRepository = $this->getContainer()->get(KeyValueBasedSettingsRepository::class);
        $general = $settingsRepository->find(SettingsGroup::GENERAL);
        $general['athlete']['ftpHistory'] = $entries;

        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::GENERAL->keyValueKey(),
            Value::fromString(Json::encode($general)),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function athleteSettings(): array
    {
        // Uncached on purpose, so assertions reflect what is actually persisted
        // rather than a memoized CachingSettingsRepository entry.
        /** @var KeyValueBasedSettingsRepository $settingsRepository */
        $settingsRepository = $this->getContainer()->get(KeyValueBasedSettingsRepository::class);

        return $settingsRepository->find(SettingsGroup::GENERAL)['athlete'];
    }

    private function rebootWithApiToken(string $token): void
    {
        $this->rebootWithEnv(['API_TOKEN' => $token]);
    }

    /**
     * @param array<string, string> $env
     */
    private function rebootWithEnv(array $env): void
    {
        self::ensureKernelShutdown();
        foreach ($env as $name => $value) {
            $_SERVER[$name] = $_ENV[$name] = $value;
            $this->overriddenEnv[] = $name;
        }
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->provideSettings();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['API_TOKEN'] = $_ENV['API_TOKEN'] = self::API_TOKEN;

        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->provideSettings();
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ([...$this->overriddenEnv, 'API_TOKEN'] as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
        }
        $this->overriddenEnv = [];

        parent::tearDown();
    }
}
