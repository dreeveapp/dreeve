<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
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

    public function deleteWidget(DashboardWidgetId $dashboardWidgetId): void
    {
        $layout = array_values(array_filter(
            iterator_to_array($this->find()),
            static fn (array $item): bool => ($item['id'] ?? null) !== (string) $dashboardWidgetId,
        ));

        $this->keyValueStore->save(KeyValue::fromState(
            Key::DASHBOARD,
            Value::fromString(Json::encode($layout)),
        ));
    }
}
