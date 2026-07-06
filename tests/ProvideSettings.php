<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Component\DependencyInjection\Container;

trait ProvideSettings
{
    abstract protected static function getContainer(): Container;

    protected function provideSettings(): void
    {
        // $this->getContainer()->get(KeyValueStore::class)->save(KeyValue::fromState(
        //     SettingsGroup::General->keyValueKey(),
        //     Value::fromString(Json::encode([...baseline...])),
        // ));
    }
}
