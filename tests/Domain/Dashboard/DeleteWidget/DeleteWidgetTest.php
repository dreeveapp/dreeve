<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\DeleteWidget;

use App\Domain\Dashboard\DeleteWidget\DeleteWidget;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class DeleteWidgetTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = DeleteWidget::fromPayload([
            'dashboardWidgetId' => '  dashboardWidget-eddington  ',
        ]);

        $this->assertSame('dashboardWidget-eddington', (string) $command->getDashboardWidgetId());
    }

    #[TestWith([[]])]
    #[TestWith([['dashboardWidgetId' => '   ']])]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "dashboardWidgetId" is required.'));

        DeleteWidget::fromPayload($payload);
    }
}
