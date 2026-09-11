<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1\Settings;

use App\Domain\Api\Token;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class AthleteWeightRequestHandlerTest extends ControllerWebTestCase
{
    private const string PATH = '/api/v1/athlete/weights';

    private Token $token;
    private SettingsRepository $settingsRepository;

    public function testItCreatesAWeightForTheSpecifiedDate(): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: Json::encode(['weight' => 71.4, 'on' => '2026-09-08']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame([
            'on' => '2026-09-08',
            'weight' => 71.4,
        ], Json::decode((string) $this->client->getResponse()->getContent()));
        $this->assertSame(['on' => '2026-09-08', 'weight' => 71.4], $this->settingsRepository->find(SettingsGroup::GENERAL, SettingsName::WEIGHT_HISTORY)[4]);
    }

    public function testItUpdatesTheExistingWeightForADate(): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: Json::encode(['weight' => 71.4, 'on' => '2020-01-01']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame([
            'on' => '2020-01-01',
            'weight' => 71.4,
        ], Json::decode((string) $this->client->getResponse()->getContent()));
        $this->assertSame([
            ['on' => '2019-12-01', 'weight' => 69],
            ['on' => '2019-08-01', 'weight' => 70],
            ['on' => '2019-07-01', 'weight' => 71],
            ['on' => '2020-01-01', 'weight' => 71.4],
        ], $this->settingsRepository->find(SettingsGroup::GENERAL, SettingsName::WEIGHT_HISTORY));
    }

    public function testItListsWeightHistory(): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: Json::encode(['weight' => 71.4, 'on' => '2026-09-08']),
        );
        $this->client->request(
            'GET',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame(['on' => '2026-09-08', 'weight' => 71.4], Json::decode((string) $this->client->getResponse()->getContent())['weights'][0]);
    }

    public function testItDeletesAWeightForADate(): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: Json::encode(['weight' => 71.4, 'on' => '2026-09-08']),
        );
        $this->client->request(
            'DELETE',
            self::PATH.'/2026-09-08',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertNotContains(['on' => '2026-09-08', 'weight' => 71.4], $this->settingsRepository->find(SettingsGroup::GENERAL, SettingsName::WEIGHT_HISTORY));
    }

    public function testItRejectsAnInvalidDateWhenDeletingAWeight(): void
    {
        $this->client->request(
            'DELETE',
            self::PATH.'/2026-02-29',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('bad_request', Json::decode((string) $this->client->getResponse()->getContent())['error']);
    }

    #[DataProvider('provideInvalidPayloads')]
    public function testItRejectsInvalidPayloads(array $payload): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: Json::encode($payload),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('bad_request', Json::decode((string) $this->client->getResponse()->getContent())['error']);
    }

    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing weight' => [[]];
        yield 'kilogram-only field' => [['weightKg' => 71.4]];
        yield 'missing date' => [['weight' => 71.4]];
        yield 'empty date' => [['weight' => 71.4, 'on' => '']];
        yield 'invalid date' => [['weight' => 71.4, 'on' => '2026-02-29']];
    }

    #[DataProvider('provideInvalidRequestBodies')]
    public function testItRejectsInvalidRequestBodies(string $content): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: $content,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('bad_request', Json::decode((string) $this->client->getResponse()->getContent())['error']);
    }

    public static function provideInvalidRequestBodies(): iterable
    {
        yield 'invalid JSON' => ['{'];
        yield 'JSON scalar' => ['71.4'];
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = Token::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        assert($settingsRepository instanceof SettingsRepository);
        $this->settingsRepository = $settingsRepository;
    }
}
