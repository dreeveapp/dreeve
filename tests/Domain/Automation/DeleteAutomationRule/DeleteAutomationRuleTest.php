<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\DeleteAutomationRule;

use App\Domain\Automation\DeleteAutomationRule\DeleteAutomationRule;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\TestCase;

class DeleteAutomationRuleTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = DeleteAutomationRule::fromPayload(['automationRuleId' => 'automationRule-42']);

        $this->assertSame('automationRule-42', (string) $command->getAutomationRuleId());
    }

    public function testThrowsOnMissingAutomationRuleId(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('An "automationRuleId" is required.'));

        DeleteAutomationRule::fromPayload([]);
    }

    public function testThrowsOnEmptyAutomationRuleId(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('An "automationRuleId" is required.'));

        DeleteAutomationRule::fromPayload(['automationRuleId' => '   ']);
    }
}
