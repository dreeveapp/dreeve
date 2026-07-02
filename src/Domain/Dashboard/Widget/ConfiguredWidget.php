<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

final readonly class ConfiguredWidget
{
    /** @var array<int, int> */
    private const array SPAN_BY_WIDTH = [33 => 4, 50 => 6, 66 => 8, 100 => 12];

    public function __construct(
        private Widget $widget,
        private WidgetConfiguration $configuration,
        private int $width,
    ) {
    }

    public function getWidget(): Widget
    {
        return $this->widget;
    }

    public function getLabel(): string
    {
        return $this->widget->getLabel();
    }

    public function getSpan(): int
    {
        return self::SPAN_BY_WIDTH[$this->width] ?? 12;
    }

    public function isConfigurable(): bool
    {
        return !$this->widget->getDefaultConfiguration()->isEmpty();
    }

    public function getConfiguration(): WidgetConfiguration
    {
        return $this->configuration;
    }

    public function getWidth(): int
    {
        return $this->width;
    }
}
