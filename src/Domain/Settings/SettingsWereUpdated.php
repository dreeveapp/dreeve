<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Infrastructure\Eventing\DomainEvent;

final class SettingsWereUpdated extends DomainEvent
{
    public function __construct(
        private readonly SettingsGroup $group,
    ) {
    }

    public function getGroup(): SettingsGroup
    {
        return $this->group;
    }
}
