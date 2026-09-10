<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1\Settings;

use App\Domain\Api\Token;
use App\Domain\Settings\SettingsGroup;
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
        $this->request(['weight' => 71.4, 'on' => '2026-09-08']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame([
            'status' => 'created',
            'on' => '2026-09-08',
            'weight' => 71.4,
        ], $this->response());
        $this->assertSame(['on' => '2026-09-08', 'weight' => 71.4], $this->weightHistory()[4]);
    }

    public function testItUpdatesTheExistingWeightForADate(): void
    {
        $this->request(['weight' => 71.4, 'on' => '2020-01-01']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame([
            'status' => 'updated',
            'on' => '2020-01-01',
            'weight' => 71.4,
        ], $this->response());
        $this->assertSame([
            ['on' => '2019-12-01', 'weight' => 69],
            ['on' => '2019-08-01', 'weight' => 70],
            ['on' => '2019-07-01', 'weight' => 71],
            ['on' => '2020-01-01', 'weight' => 71.4],
        ], $this->weightHistory());
    }

    public function testItListsWeightHistory(): void
    {
        $this->request(['weight' => 71.4, 'on' => '2026-09-08']);
        $this->client->request(
            'GET',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame(['on' => '2026-09-08', 'weight' => 71.4], $this->response()['weights'][0]);
    }

    public function testItDeletesAWeightForADate(): void
    {
        $this->request(['weight' => 71.4, 'on' => '2026-09-08']);
        $this->client->request(
            'DELETE',
            self::PATH.'/2026-09-08',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertNotContains(['on' => '2026-09-08', 'weight' => 71.4], $this->weightHistory());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testItRejectsInvalidPayloads(array $payload): void
    {
        $this->request($payload);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('bad_request', $this->response()['error']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing weight' => [[]];
        yield 'kilogram-only field' => [['weightKg' => 71.4]];
        yield 'missing date' => [['weight' => 71.4]];
        yield 'empty date' => [['weight' => 71.4, 'on' => '']];
        yield 'invalid date' => [['weight' => 71.4, 'on' => '2026-02-29']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: Json::encode($payload),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function response(): array
    {
        return Json::decode((string) $this->client->getResponse()->getContent());
    }

    /**
     * @return list<array{on: string, weight: float|int}>
     */
    private function weightHistory(): array
    {
        return $this->settingsRepository->find(SettingsGroup::GENERAL)['athlete']['weightHistory'];
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
