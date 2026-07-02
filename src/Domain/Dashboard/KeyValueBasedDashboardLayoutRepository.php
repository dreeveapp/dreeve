<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;

final readonly class KeyValueBasedDashboardLayoutRepository implements DashboardLayoutRepository
{
    public function __construct(
        private KeyValueStore $keyValueStore,
    ) {
    }

    public function find(): DashboardLayout
    {
        try {
            /** @var array<int, mixed>|null $config */
            $config = Json::decode((string) $this->keyValueStore->find(Key::DASHBOARD));
        } catch (EntityNotFound) {
            $config = null;
        }

        return DashboardLayout::fromArray(is_array($config) ? $config : null);
    }
}
