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

        // Baseline the whole suite gets today from config/app/test/ (config.yaml + config-athlete.yaml),
        // normalized to camelCase. "appUrl" stays env-driven, so it is not stored here.
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
                        '2020-01-01' => 68,
                        '2019-12-01' => 69,
                        '2019-08-01' => 70,
                        '2019-07-01' => 71,
                    ],
                    'ftpHistory' => [
                        'cycling' => [
                            '2023-01-01' => 198,
                            '2023-03-22' => 220,
                            '2023-03-29' => 238,
                            '2023-04-01' => 250,
                        ],
                        'running' => [
                            '2023-01-01' => 100,
                            '2023-03-22' => 110,
                            '2023-03-29' => 120,
                            '2023-04-01' => 130,
                        ],
                    ],
                ],
            ])),
        ));
    }
}
