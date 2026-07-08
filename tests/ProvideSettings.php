<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\DependencyInjection\Container;

trait ProvideSettings
{
    abstract protected static function getContainer(): Container;

    protected function provideSettings(): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);

        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::GENERAL->keyValueKey(),
            Value::fromString(Json::encode([
                'profilePictureUrl' => null,
                'appSubTitle' => 'Robin The King 👑',
                'athlete' => [
                    'birthday' => '1989-08-14',
                    'firstName' => 'Robin',
                    'lastName' => 'Ingelbrecht',
                    'maxHeartRateFormula' => 'fox',
                    'weightHistory' => [
                        ['on' => '2020-01-01', 'weight' => 68],
                        ['on' => '2019-12-01', 'weight' => 69],
                        ['on' => '2019-08-01', 'weight' => 70],
                        ['on' => '2019-07-01', 'weight' => 71],
                    ],
                    'ftpHistory' => [
                        'cycling' => [
                            ['on' => '2023-01-01', 'ftp' => 198],
                            ['on' => '2023-03-22', 'ftp' => 220],
                            ['on' => '2023-03-29', 'ftp' => 238],
                            ['on' => '2023-04-01', 'ftp' => 250],
                        ],
                        'running' => [
                            ['on' => '2023-01-01', 'ftp' => 100],
                            ['on' => '2023-03-22', 'ftp' => 110],
                            ['on' => '2023-03-29', 'ftp' => 120],
                            ['on' => '2023-04-01', 'ftp' => 130],
                        ],
                    ],
                ],
            ])),
        ));

        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::APPEARANCE->keyValueKey(),
            Value::fromString(Json::encode([
                'locale' => 'en_US',
                'unitSystem' => 'metric',
                'timeFormat' => 24,
                'dateFormat' => [
                    'short' => 'd-m-y',
                    'normal' => 'd-m-Y',
                ],
            ])),
        ));

        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::IMPORT->keyValueKey(),
            Value::fromString(Json::encode([
                'numberOfNewActivitiesToProcessPerImport' => 250,
                'sportTypesToImport' => [],
                'activityVisibilitiesToImport' => [],
                'skipActivitiesRecordedBefore' => null,
                'activitiesToSkipDuringImport' => ['skip'],
                'optInToSegmentDetailImport' => true,
                'webhooks' => [
                    'enabled' => true,
                    'verifyToken' => 'ffc26d52-d3ff-4797-a2b7-780a593a3547',
                ],
            ])),
        ));

        // Baseline the whole suite gets today from config/app/test/config.yaml, normalized to camelCase.
        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::ZWIFT->keyValueKey(),
            Value::fromString(Json::encode([
                'level' => 80,
                'racingScore' => 495,
            ])),
        ));

        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::INTEGRATIONS->keyValueKey(),
            Value::fromString(Json::encode([
                'notifications' => [
                    'services' => [
                        // The deprecated ntfy config is folded into the services list by the migration.
                        'ntfy://admin:pass@ntfy.sh/el-test',
                        'ntfy://admin:admin@ntfy.sh/topic',
                        'discord://token@webhookid?thread_id=123456789',
                        'telegram://token@telegram/?channels=channel',
                    ],
                ],
            ])),
        ));
    }
}
