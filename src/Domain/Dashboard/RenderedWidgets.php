<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Infrastructure\Time\Clock\Clock;

final readonly class RenderedWidgets implements \IteratorAggregate
{
    public function __construct(
        private ConfiguredWidgets $configuredWidgets,
        private Clock $clock,
    ) {
    }

    /**
     * @return \Traversable<RenderedWidget>
     */
    public function getIterator(): \Traversable
    {
        $renderedWidgets = [];
        foreach ($this->configuredWidgets as $configuredWidget) {
            $render = $configuredWidget->getWidget()->render(
                $this->clock->getCurrentDateTimeImmutable(),
                $configuredWidget->getConfiguration(),
            );
            if (!$render) {
                continue;
            }

            $renderedWidgets[] = new RenderedWidget(
                renderedHtml: $render,
                width: $configuredWidget->getWidth(),
            );
        }

        return new \ArrayIterator($renderedWidgets);
    }
}
