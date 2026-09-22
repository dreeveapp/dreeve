<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use Twig\Environment;

final readonly class WidgetRenderer
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(Widget $widget, WidgetConfiguration $configuration, array $context = []): string
    {
        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $widget->getTemplateName()))->render([
            'widgetTitle' => $configuration->getTitle() ?? $widget->getLabel(),
            ...$context,
        ]);
    }
}
