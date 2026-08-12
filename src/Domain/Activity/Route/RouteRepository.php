<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

interface RouteRepository
{
    public function findAll(): Routes;

    public function findSummary(): RouteSummary;
}
