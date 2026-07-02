<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Dashboard\DashboardLayoutRepository;
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
            $widget->guardValidConfiguration($configuration);

            $configuredWidgets[] = new ConfiguredWidget(
                widget: $widget,
                configuration: $configuration,
                width: $layoutItem['width'],
            );
        }

        return new \ArrayIterator($configuredWidgets);
    }
}
