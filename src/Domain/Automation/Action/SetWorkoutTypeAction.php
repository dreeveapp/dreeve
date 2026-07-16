<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Activity\WorkoutType;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;

final readonly class SetWorkoutTypeAction implements Action
{
    public function getLabel(): string
    {
        return 'Set workout type';
    }

    public function getTemplateName(): string
    {
        return 'automation-action--set-workout-type';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'workoutType' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $workoutType = $configuration->get('workoutType');
        if (!is_string($workoutType) || null === WorkoutType::tryFrom($workoutType)) {
            throw new InvalidAutomationRule(sprintf('Invalid workout type "%s".', is_scalar($workoutType) ? (string) $workoutType : ''));
        }
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        $workoutType = $configuration->get('workoutType');
        assert(is_string($workoutType));

        return $activity->withWorkoutType(WorkoutType::from($workoutType));
    }
}
