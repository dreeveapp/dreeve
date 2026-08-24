<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindGroupActivityCount;

use App\Infrastructure\CQRS\Query\Response;

final readonly class FindGroupActivityCountResponse implements Response
{
    public function __construct(
        private int $groupActivityCount,
    ) {
    }

    public function getGroupActivityCount(): int
    {
        return $this->groupActivityCount;
    }
}
