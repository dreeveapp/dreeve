<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Automation\InvalidAutomationRule;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class Actions
{
    /** @var array<string, Action> */
    private array $actions = [];

    /**
     * @param iterable<Action> $actions
     */
    public function __construct(
        #[AutowireIterator('app.automation_rule.action')]
        iterable $actions,
    ) {
        foreach ($actions as $action) {
            $this->actions[$this->typeOf($action)->value] = $action;
        }
    }

    public function has(ActionType $type): bool
    {
        return array_key_exists($type->value, $this->actions);
    }

    public function get(ActionType $type): Action
    {
        return $this->actions[$type->value]
            ?? throw new InvalidAutomationRule(sprintf('No action registered for type "%s".', $type->value));
    }

    /**
     * @return array<string, Action>
     */
    public function all(): array
    {
        $actions = $this->actions;
        uasort($actions, static fn (Action $a, Action $b): int => $a->getPriority() <=> $b->getPriority());

        return $actions;
    }

    private function typeOf(Action $action): ActionType
    {
        return ActionType::from(lcfirst(str_replace('Action', '', new \ReflectionClass($action)->getShortName())));
    }
}
