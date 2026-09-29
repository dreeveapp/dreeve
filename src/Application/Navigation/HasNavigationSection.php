<?php

declare(strict_types=1);

namespace App\Application\Navigation;

interface HasNavigationSection
{
    public function getNavigationSection(): ?NavigationSection;
}
