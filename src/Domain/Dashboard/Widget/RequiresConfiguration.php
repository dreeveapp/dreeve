<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

interface RequiresConfiguration
{
    public function configurationIsEmpty(WidgetConfiguration $configuration): bool;
}
