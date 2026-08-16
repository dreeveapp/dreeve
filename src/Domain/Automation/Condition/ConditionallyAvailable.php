<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

interface ConditionallyAvailable
{
    public function isAvailable(): bool;
}
