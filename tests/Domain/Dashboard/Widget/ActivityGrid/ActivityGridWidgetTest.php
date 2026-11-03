<?php

namespace App\Tests\Domain\Dashboard\Widget\ActivityGrid;

use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\ActivityGrid\ActivityGridWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ActivityGridWidgetTest extends ContainerTestCase
{
    private ActivityGridWidget $widget;

    #[DataProvider('provideInvalidConfig')]
    public function testGuardValidConfigurationItShouldThrow(WidgetConfiguration $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidDashboardLayout($expectedException));
        $this->widget->guardValidConfiguration($config);
    }

    public static function provideInvalidConfig(): iterable
    {
        $config = WidgetConfiguration::empty();
        yield 'missing "metricsDisplayOrder" key' => [$config, 'Configuration item "metricsDisplayOrder" is required for ActivityGridWidget.'];

        $config = WidgetConfiguration::empty()
            ->add('metricsDisplayOrder', 'invalid');
        yield 'invalid "metricsDisplayOrder" key' => [$config, 'Configuration item "metricsDisplayOrder" must be an array.'];

        $config = WidgetConfiguration::empty()
            ->add('metricsDisplayOrder', [1, 2, 3, 4]);
        yield 'invalid number of items in "metricsDisplayOrder"' => [$config, 'Configuration item "metricsDisplayOrder" must contain all 3 grid types.'];

        $config = WidgetConfiguration::empty()
            ->add('metricsDisplayOrder', ['test', 2, 3]);
        yield 'invalid value in "metricsDisplayOrder"' => [$config, 'Configuration item "metricsDisplayOrder" contains invalid value "test".'];
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->widget = $this->getContainer()->get(ActivityGridWidget::class);
    }
}
