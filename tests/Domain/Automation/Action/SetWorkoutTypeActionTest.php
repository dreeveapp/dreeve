<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Activity\WorkoutType;
use App\Domain\Automation\Action\SetWorkoutTypeAction;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class SetWorkoutTypeActionTest extends TestCase
{
    private SetWorkoutTypeAction $action;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['workoutType' => ''],
            $this->action->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration($this->config('race'));
    }

    public function testGuardThrowsOnInvalidWorkoutType(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Invalid workout type "nope".'));

        $this->action->guardValidConfiguration($this->config('nope'));
    }

    public function testApplyToSetsTheWorkoutType(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, $this->config('race'));

        $this->assertSame(WorkoutType::RACE, $activity->getWorkoutType());
    }

    private function config(string $workoutType): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['workoutType' => $workoutType]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SetWorkoutTypeAction();
    }
}
