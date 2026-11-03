<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\MostRecentActivitiesWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MostRecentActivitiesWidgetTest extends ContainerTestCase
{
    private MostRecentActivitiesWidget $widget;

    #[DataProvider('provideInvalidConfig')]
    public function testGuardValidConfigurationItShouldThrow(WidgetConfiguration $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidDashboardLayout($expectedException));
        $this->widget->guardValidConfiguration($config);
    }

    public static function provideInvalidConfig(): iterable
    {
        yield 'missing "numberOfActivitiesToDisplay" key' => [WidgetConfiguration::empty(), 'Configuration item "numberOfActivitiesToDisplay" is required for MostRecentActivitiesWidget.'];
        $config = WidgetConfiguration::empty()
            ->add('numberOfActivitiesToDisplay', 'lol');
        yield 'invalid "numberOfActivitiesToDisplay" key' => [$config, 'Configuration item "numberOfActivitiesToDisplay" must be an integer.'];
        $config = WidgetConfiguration::empty()
            ->add('numberOfActivitiesToDisplay', 0);
        yield 'too small "numberOfActivitiesToDisplay" key' => [$config, 'Configuration item "numberOfActivitiesToDisplay" must be set to a value of 1 or greater.'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = $this->getContainer()->get(MostRecentActivitiesWidget::class);
    }
}
