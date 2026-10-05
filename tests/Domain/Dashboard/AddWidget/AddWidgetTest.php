<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\AddWidget;

use App\Domain\Dashboard\AddWidget\AddWidget;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class AddWidgetTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = AddWidget::fromPayload([
            'widget' => '  eddington  ',
        ]);

        $this->assertSame('eddington', (string) $command->getWidgetName());
    }

    #[TestWith([[]])]
    #[TestWith([['widget' => '   ']])]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "widget" is required.'));

        AddWidget::fromPayload($payload);
    }
}
