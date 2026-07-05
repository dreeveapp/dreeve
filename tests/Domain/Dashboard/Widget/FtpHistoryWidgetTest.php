<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\Widget\FtpHistoryWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class FtpHistoryWidgetTest extends ContainerTestCase
{
    use ProvideTestData;
    use MatchesSnapshots;

    private FtpHistoryWidget $widget;

    public function testRender(): void
    {
        $this->assertNull($this->widget->render(
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty(),
        ));
    }

    public function testGuardValidConfigurationItShouldNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->widget->guardValidConfiguration(WidgetConfiguration::empty());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = $this->getContainer()->get(FtpHistoryWidget::class);
    }
}
