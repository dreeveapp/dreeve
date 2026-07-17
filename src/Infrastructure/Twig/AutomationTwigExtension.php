<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class AutomationTwigExtension
{
    public function __construct(
        private Conditions $conditions,
        private Actions $actions,
        private TranslatorInterface $translator,
    ) {
    }

    #[AsTwigFunction('describe_condition')]
    public function describeCondition(ConditionType $type, RuleConfiguration $configuration): string
    {
        if (!$this->conditions->has($type)) {
            return $type->value;
        }

        return $this->conditions->get($type)->describe(
            translator: $this->translator,
            configuration: $configuration
        );
    }

    #[AsTwigFunction('describe_action')]
    public function describeAction(ActionType $type, RuleConfiguration $configuration): string
    {
        if (!$this->actions->has($type)) {
            return $type->value;
        }

        return $this->actions->get($type)->describe(
            translator: $this->translator,
            configuration: $configuration
        );
    }
}
