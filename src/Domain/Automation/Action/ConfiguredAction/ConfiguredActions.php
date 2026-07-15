<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action\ConfiguredAction;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ConfiguredAction>
 */
final class ConfiguredActions extends Collection
{
    public function getItemClassName(): string
    {
        return ConfiguredAction::class;
    }
}
