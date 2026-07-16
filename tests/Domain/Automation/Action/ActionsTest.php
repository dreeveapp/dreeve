<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\InvalidAutomationRule;
use App\Tests\Domain\Automation\Fixture\SetDescriptionAction;
use App\Tests\Domain\Automation\Fixture\SetNameAction;
use PHPUnit\Framework\TestCase;

class ActionsTest extends TestCase
{
    private Actions $actions;

    public function testHas(): void
    {
        $this->assertTrue($this->actions->has(ActionType::SET_NAME));
        $this->assertTrue($this->actions->has(ActionType::SET_DESCRIPTION));
        $this->assertFalse($this->actions->has(ActionType::ASSIGN_GEAR));
    }

    public function testGetReturnsTheServiceKeyedByItsType(): void
    {
        $this->assertInstanceOf(SetNameAction::class, $this->actions->get(ActionType::SET_NAME));
        $this->assertInstanceOf(SetDescriptionAction::class, $this->actions->get(ActionType::SET_DESCRIPTION));
    }

    public function testGetThrowsForUnregisteredType(): void
    {
        $this->expectExceptionObject(
            new InvalidAutomationRule('No action registered for type "assignGear".')
        );

        $this->actions->get(ActionType::ASSIGN_GEAR);
    }

    public function testAllIsSortedByPriority(): void
    {
        $this->assertSame(
            [ActionType::SET_NAME->value, ActionType::SET_DESCRIPTION->value],
            array_keys($this->actions->all())
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->actions = new Actions([
            new SetNameAction(),
            new SetDescriptionAction(),
        ]);
    }
}
