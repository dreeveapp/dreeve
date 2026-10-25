<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\DaytimeStats\DayTimeStatsWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class DayTimeStatsWidgetTest extends ContainerTestCase
{
    private DayTimeStatsWidget $widget;

    public function testGuardValidConfigurationAcceptsANightThatStartsAfterMidnight(): void
    {
        $this->widget->guardValidConfiguration(WidgetConfiguration::empty()->add('morningStartHour', 4)->add('afternoonStartHour', 11)->add('eveningStartHour', 17)->add('nightStartHour', 1));
        $this->addToAssertionCount(1);
    }

    #[DataProvider(methodName: 'provideInvalidConfig')]
    public function testGuardValidConfigurationItShouldThrow(WidgetConfiguration $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidDashboardLayout($expectedException));
        $this->widget->guardValidConfiguration($config);
    }

    public static function provideInvalidConfig(): iterable
    {
        yield 'missing "morningStartHour" key' => [WidgetConfiguration::empty(), 'Configuration item "morningStartHour" is required for DayTimeStatsWidget.'];
        yield 'invalid "afternoonStartHour" key' => [WidgetConfiguration::empty()->add('morningStartHour', 6)->add('afternoonStartHour', 'lol')->add('eveningStartHour', 17)->add('nightStartHour', 23), 'Configuration item "afternoonStartHour" must be an integer.'];
        yield 'too small "eveningStartHour" key' => [WidgetConfiguration::empty()->add('morningStartHour', 6)->add('afternoonStartHour', 12)->add('eveningStartHour', -1)->add('nightStartHour', 23), 'Configuration item "eveningStartHour" must be between 0 and 23.'];
        yield 'too large "nightStartHour" key' => [WidgetConfiguration::empty()->add('morningStartHour', 6)->add('afternoonStartHour', 12)->add('eveningStartHour', 17)->add('nightStartHour', 24), 'Configuration item "nightStartHour" must be between 0 and 23.'];
        yield 'afternoon before morning' => [WidgetConfiguration::empty()->add('morningStartHour', 12)->add('afternoonStartHour', 6)->add('eveningStartHour', 17)->add('nightStartHour', 23), 'The daytime start hours must follow each other in the order morning, afternoon, evening, night.'];
        yield 'night past morning' => [WidgetConfiguration::empty()->add('morningStartHour', 6)->add('afternoonStartHour', 12)->add('eveningStartHour', 17)->add('nightStartHour', 7), 'The daytime start hours must follow each other in the order morning, afternoon, evening, night.'];
        yield 'two daytimes start at the same hour' => [WidgetConfiguration::empty()->add('morningStartHour', 6)->add('afternoonStartHour', 12)->add('eveningStartHour', 12)->add('nightStartHour', 23), 'The daytime start hours must follow each other in the order morning, afternoon, evening, night.'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = $this->getContainer()->get(DayTimeStatsWidget::class);
    }
}
