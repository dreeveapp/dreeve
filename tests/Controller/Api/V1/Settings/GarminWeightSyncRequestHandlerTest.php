<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1\Settings;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Security\Token\ApiToken;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class GarminWeightSyncRequestHandlerTest extends ControllerWebTestCase
{
    private const string PATH = '/api/v1/athlete/weights/garmin';
    private ApiToken $token;

    public function testSyncIsDisabledByDefaultAndRequiresAuthentication(): void
    {
        $this->client->request('GET', self::PATH);
        $this->assertResponseStatusCodeSame(401);
        $this->request('GET');
        $this->assertResponseIsSuccessful();
        $this->assertSame(['enabled' => false], Json::decode((string) $this->client->getResponse()->getContent()));
        $this->request('POST', ['weights' => [['on' => '2026-11-25', 'weightKg' => 70.125]]]);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testRepeatedSyncIsIdempotentAndNeverReplacesManualWeights(): void
    {
        $settings = $this->getContainer()->get(SettingsRepository::class);
        $settings->save(SettingsName::GARMIN_WEIGHT_SYNC_ENABLED, true);
        $manual = $settings->find(SettingsName::WEIGHT_HISTORY);
        $payload = ['weights' => [
            ['on' => '2020-01-01', 'weightKg' => 99],
            ['on' => '2026-11-25', 'weightKg' => 70.125],
        ]];
        $this->request('POST', $payload);
        $this->assertResponseIsSuccessful();
        $this->request('POST', $payload);
        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $settings->find(SettingsName::GARMIN_WEIGHT_HISTORY));
        $this->assertSame($manual, $settings->find(SettingsName::WEIGHT_HISTORY));
        $effective = array_values($settings->athleteWeight()->getHistory(UnitSystem::METRIC)->findAll());
        $this->assertSame(70.125, $effective[0]->getWeight()->toFloat());
        $this->assertSame(68.0, $effective[1]->getWeight()->toFloat());
    }

    public function testSavingTheManualEditorKeepsImportedHistoryAndCanDisableSync(): void
    {
        $settings = $this->getContainer()->get(SettingsRepository::class);
        $settings->save(SettingsName::GARMIN_WEIGHT_SYNC_ENABLED, true);
        $this->request('POST', ['weights' => [['on' => '2026-11-25', 'weightKg' => 70.125]]]);
        $this->getContainer()->get(CommandBus::class)->dispatch(UpdateSettings::fromPayload([
            'group' => SettingsGroup::ATHLETE_WEIGHT->value,
            'data' => ['weightHistory' => [['on' => '2026-11-25', 'weight' => 71]], 'garminWeightSyncEnabled' => '0'],
        ]));
        $this->assertSame([['on' => '2026-11-25', 'weightKg' => 70.125]], $settings->find(SettingsName::GARMIN_WEIGHT_HISTORY));
        $this->assertFalse($settings->athleteWeight()->isGarminWeightSyncEnabled());
        $this->request('POST', ['weights' => [['on' => '2026-11-25', 'weightKg' => 69]]]);
        $this->assertResponseStatusCodeSame(409);
        $weight = array_first($settings->athleteWeight()->getHistory(UnitSystem::METRIC)->findAll());
        $this->assertNotNull($weight);
        $this->assertSame(71.0, $weight->getWeight()->toFloat());
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function testInvalidBatchesAreRejectedAtomically(array $payload): void
    {
        $settings = $this->getContainer()->get(SettingsRepository::class);
        $settings->save(SettingsName::GARMIN_WEIGHT_SYNC_ENABLED, true);
        $this->request('POST', $payload);
        $this->assertResponseStatusCodeSame(400);
        $this->assertNull($settings->find(SettingsName::GARMIN_WEIGHT_HISTORY));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): iterable
    {
        yield 'missing weights' => [[]];
        yield 'bad date' => [['weights' => [['on' => '2026-02-29', 'weightKg' => 70]]]];
        yield 'negative weight' => [['weights' => [['on' => '2026-11-25', 'weightKg' => -1]]]];
        yield 'mixed validity' => [['weights' => [['on' => '2026-11-25', 'weightKg' => 70], ['on' => 'bad', 'weightKg' => 71]]]];
    }

    /** @param array<string, mixed>|null $payload */
    private function request(string $method, ?array $payload = null): void
    {
        $this->client->request($method, self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: null === $payload ? null : Json::encode($payload),
        );
    }

    protected function prepareEnvironment(): void
    {
        $this->token = ApiToken::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}
