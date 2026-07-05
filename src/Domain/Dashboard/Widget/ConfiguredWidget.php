<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Dashboard\DashboardWidgetId;

final readonly class ConfiguredWidget
{
    public function __construct(
        private DashboardWidgetId $id,
        private WidgetName $name,
        private Widget $widget,
        private WidgetConfiguration $configuration,
        private int $width,
    ) {
    }

    public function getId(): DashboardWidgetId
    {
        return $this->id;
    }

    public function getName(): WidgetName
    {
        return $this->name;
    }

    public function getWidget(): Widget
    {
        return $this->widget;
    }

    public function getLabel(): string
    {
        return $this->widget->getLabel();
    }

    public function isConfigurable(): bool
    {
        return !$this->widget->getDefaultConfiguration()->isEmpty();
    }

    public function getConfigurationTemplate(): string
    {
        return sprintf(
            'html/admin/page/settings/dashboard/widget-config/%s.html.twig',
            $this->widget->getTemplateName(),
        );
    }

    public function hasWideConfigurationForm(): bool
    {
        return $this->widget instanceof HasWideConfigurationForm;
    }

    public function stillNeedsConfiguration(): bool
    {
        return $this->widget instanceof RequiresConfiguration
            && $this->widget->configurationIsEmpty($this->configuration);
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
