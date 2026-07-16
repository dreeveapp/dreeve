<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Automation\Action\SetNameAction;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class SetNameActionTest extends TestCase
{
    private SetNameAction $action;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['name' => ''],
            $this->action->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration($this->config('Morning Ride'));
    }

    public function testGuardThrowsOnEmptyName(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('A "name" is required.'));

        $this->action->guardValidConfiguration($this->config('   '));
    }

    public function testApplyToSetsTheName(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, $this->config('Morning Ride'));

        $this->assertSame('Morning Ride', $activity->getName());
    }

    private function config(string $name): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['name' => $name]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SetNameAction();
    }
}
