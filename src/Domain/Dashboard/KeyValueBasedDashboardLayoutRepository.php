<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Domain\Dashboard\Widget\WidgetName;
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

    public function addWidget(DashboardWidgetId $dashboardWidgetId, WidgetName $widgetName, int $width): void
    {
        $layout = iterator_to_array($this->find());
        $layout[] = [
            'id' => (string) $dashboardWidgetId,
            'widget' => (string) $widgetName,
            'width' => $width,
        ];

        $this->keyValueStore->save(KeyValue::fromState(
            Key::DASHBOARD,
            Value::fromString(Json::encode($layout)),
        ));
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

    public function saveLayout(array $orderedWidgets): void
    {
        $stored = [];
        foreach (iterator_to_array($this->find()) as $item) {
            $stored[$item['id']] = $item;
        }

        $layout = [];
        foreach ($orderedWidgets as $orderedWidget) {
            $id = $orderedWidget['id'];
            if (!isset($stored[$id])) {
                // Ignore ids that are no longer part of the stored layout (e.g. stale page).
                continue;
            }

            $item = $stored[$id];
            $item['width'] = $orderedWidget['width'];
            $layout[] = $item;
            unset($stored[$id]);
        }

        // Preserve any stored widgets that were not part of the payload (e.g. added in another
        // tab while this page was open) by appending them in their existing relative order.
        foreach ($stored as $item) {
            $layout[] = $item;
        }

        $this->keyValueStore->save(KeyValue::fromState(
            Key::DASHBOARD,
            Value::fromString(Json::encode($layout)),
        ));
    }

    public function resetToDefault(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::DASHBOARD,
            Value::fromString(Json::encode(DashboardLayout::default())),
        ));
    }

    public function updateWidgetConfiguration(DashboardWidgetId $dashboardWidgetId, array $configuration): void
    {
        $layout = array_map(
            static function (array $item) use ($dashboardWidgetId, $configuration): array {
                if (($item['id'] ?? null) === (string) $dashboardWidgetId) {
                    $item['config'] = $configuration;
                }

                return $item;
            },
            iterator_to_array($this->find()),
        );

        $this->keyValueStore->save(KeyValue::fromState(
            Key::DASHBOARD,
            Value::fromString(Json::encode($layout)),
        ));
    }
}
