<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Match;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<RouteMatch>
 */
final class RouteMatches extends Collection
{
    public function getItemClassName(): string
    {
        return RouteMatch::class;
    }
}
