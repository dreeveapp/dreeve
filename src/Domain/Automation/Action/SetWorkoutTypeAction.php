<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Activity\WorkoutType;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SetWorkoutTypeAction implements Action
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Set workout type', domain: 'admin', locale: $locale);
    }

    public function describe(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return $translator->trans('Set workout type to {workoutType}', [
            'workoutType' => WorkoutType::from($configuration->getString('workoutType'))->trans($translator),
        ], 'admin');
    }

    public function getPriority(): int
    {
        return 40;
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
        $workoutType = $configuration->getString('workoutType');

        return $activity->withWorkoutType(WorkoutType::from($workoutType));
    }
}
