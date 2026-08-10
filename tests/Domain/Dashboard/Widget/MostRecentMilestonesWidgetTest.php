<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\MostRecentMilestonesWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MostRecentMilestonesWidgetTest extends ContainerTestCase
{
    private MostRecentMilestonesWidget $widget;

    #[DataProvider(methodName: 'provideInvalidConfig')]
    public function testGuardValidConfigurationItShouldThrow(WidgetConfiguration $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidDashboardLayout($expectedException));
        $this->widget->guardValidConfiguration($config);
    }

    public function testWhenEmptyMilestones(): void
    {
        $this->assertEmpty(
            $this->widget->render(
                dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
                now: SerializableDateTime::fromString('2025-12-02'),
                configuration: WidgetConfiguration::empty()
            )
        );
    }

    public static function provideInvalidConfig(): iterable
    {
        yield 'missing "numberOfMilestonesToDisplay" key' => [WidgetConfiguration::empty(), 'Configuration item "numberOfMilestonesToDisplay" is required for MostRecentMilestonesWidget.'];
        $config = WidgetConfiguration::empty()
            ->add('numberOfMilestonesToDisplay', 'lol');
        yield 'invalid "numberOfMilestonesToDisplay" key' => [$config, 'Configuration item "numberOfMilestonesToDisplay" must be an integer.'];
        $config = WidgetConfiguration::empty()
            ->add('numberOfMilestonesToDisplay', 0);
        yield 'too small "numberOfMilestonesToDisplay" key' => [$config, 'Configuration item "numberOfMilestonesToDisplay" must be set to a value of 1 or greater.'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = $this->getContainer()->get(MostRecentMilestonesWidget::class);
    }
}
