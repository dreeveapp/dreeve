<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Dashboard\DashboardLayoutRepository;
use App\Domain\Dashboard\DashboardWidgetId;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ConfiguredWidgets implements \IteratorAggregate
{
    /** @var array<string, Widget> */
    private array $widgets = [];
    /** @var array<int, int> */
    public const array WIDTHS = [33, 50, 66, 100];

    /**
     * @param iterable<Widget> $widgets
     */
    public function __construct(
        #[AutowireIterator('app.dashboard.widget')]
        iterable $widgets,
        private readonly DashboardLayoutRepository $dashboardLayoutRepository,
    ) {
        foreach ($widgets as $widget) {
            $this->widgets[(string) WidgetName::fromWidgetInstance($widget)] = $widget;
        }
    }

    /**
     * @return \Traversable<ConfiguredWidget>
     */
    public function getIterator(): \Traversable
    {
        $configuredWidgets = [];
        foreach ($this->dashboardLayoutRepository->find() as $layoutItem) {
            $widgetName = WidgetName::fromConfigValue($layoutItem['widget']);
            if ($widgetName->wasRemoved()) {
                continue;
            }
            $widget = $this->widgets[(string) $widgetName] ?? throw new \InvalidArgumentException(sprintf('Dashboard widget "%s" does not exists.', $widgetName));

            $configuration = $widget->getDefaultConfiguration();
            foreach ($layoutItem['config'] ?? [] as $key => $value) {
                $configuration->add($key, $value);
            }

            $configuredWidgets[] = new ConfiguredWidget(
                id: DashboardWidgetId::fromString($layoutItem['id']),
                name: $widgetName,
                widget: $widget,
                configuration: $configuration,
                width: $layoutItem['width'],
            );
        }

        return new \ArrayIterator($configuredWidgets);
    }

    /**
     * @return array<string, Widget>
     */
    public function getAvailableWidgets(): array
    {
        $widgets = $this->widgets;
        uasort($widgets, static fn (Widget $a, Widget $b): int => strcasecmp($a->getLabel(), $b->getLabel()));

        return $widgets;
    }

    public function hasAvailableWidget(WidgetName $widgetName): bool
    {
        return array_key_exists((string) $widgetName, $this->widgets);
    }

    /**
     * @return array<string, int>
     */
    public function getConfiguredWidgetCountPerType(): array
    {
        $counts = [];
        foreach ($this as $configuredWidget) {
            $widgetName = (string) $configuredWidget->getName();
            $counts[$widgetName] ??= 0;
            ++$counts[$widgetName];
        }

        return $counts;
    }

    public function find(DashboardWidgetId $dashboardWidgetId): ?ConfiguredWidget
    {
        foreach ($this as $configuredWidget) {
            if ((string) $configuredWidget->getId() === (string) $dashboardWidgetId) {
                return $configuredWidget;
            }
        }

        return null;
    }
}
