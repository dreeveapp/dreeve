<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Match\FindRouteMatches;

use App\Domain\Activity\Route\Match\RouteMatches;
use App\Infrastructure\CQRS\Query\Response;

final readonly class FindRouteMatchesResponse implements Response
{
    public function __construct(
        private RouteMatches $routeMatches,
    ) {
    }

    public function getRouteMatches(): RouteMatches
    {
        return $this->routeMatches;
    }
}
