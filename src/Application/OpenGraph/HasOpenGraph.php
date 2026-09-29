<?php

declare(strict_types=1);

namespace App\Application\OpenGraph;

interface HasOpenGraph
{
    public function getOpenGraph(): ?OpenGraph;
}
