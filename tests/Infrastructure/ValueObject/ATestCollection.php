<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ValueObject;

use App\Infrastructure\ValueObject\Collection;
use App\Infrastructure\ValueObject\String\Name;

/**
 * @extends Collection<mixed>
 */
class ATestCollection extends Collection
{
    public function getItemClassName(): string
    {
        return Name::class;
    }
}
