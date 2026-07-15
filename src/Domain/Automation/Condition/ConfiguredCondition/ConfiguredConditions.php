<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition\ConfiguredCondition;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ConfiguredCondition>
 */
final class ConfiguredConditions extends Collection
{
    public function getItemClassName(): string
    {
        return ConfiguredCondition::class;
    }
}
