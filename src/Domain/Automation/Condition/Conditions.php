<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Automation\InvalidAutomationRule;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class Conditions
{
    /** @var array<string, Condition> */
    private array $conditions = [];

    /**
     * @param iterable<Condition> $conditions
     */
    public function __construct(
        #[AutowireIterator('app.automation_rule.condition')]
        iterable $conditions,
    ) {
        foreach ($conditions as $condition) {
            $this->conditions[$this->typeOf($condition)->value] = $condition;
        }
    }

    public function has(ConditionType $type): bool
    {
        return array_key_exists($type->value, $this->conditions);
    }

    public function get(ConditionType $type): Condition
    {
        return $this->conditions[$type->value] ?? throw new InvalidAutomationRule(sprintf('No condition registered for type "%s".', $type->value));
    }

    /**
     * @return array<string, Condition>
     */
    public function all(): array
    {
        $conditions = $this->conditions;
        uasort($conditions, static fn (Condition $a, Condition $b): int => $a->getPriority() <=> $b->getPriority());

        return $conditions;
    }

    private function typeOf(Condition $condition): ConditionType
    {
        return ConditionType::from(lcfirst(str_replace('Condition', '', new \ReflectionClass($condition)->getShortName())));
    }
}
