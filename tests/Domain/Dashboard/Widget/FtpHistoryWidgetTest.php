<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\FtpHistoryWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;

class FtpHistoryWidgetTest extends ContainerTestCase
{
    use ProvideTestData;

    private FtpHistoryWidget $widget;

    public function testRender(): void
    {
        $this->assertNull($this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = $this->getContainer()->get(FtpHistoryWidget::class);
    }
}
