<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Tests\Domain\Activity\ActivityBuilder;
use Symfony\Component\DependencyInjection\Container;

trait ProvideBuiltApp
{
    abstract protected static function getContainer(): Container;

    protected function markAppAsBuilt(): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILD_SNAPSHOT,
            value: Value::fromString('2023-10-17@1.0.0'),
        ));

        /** @var ActivityIdRepository $activityIdRepository */
        $activityIdRepository = $this->getContainer()->get(ActivityIdRepository::class);
        if ($activityIdRepository->count() > 0) {
            return;
        }

        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->build(),
            [],
        ));
    }
}
