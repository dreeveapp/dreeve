<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\RollingStats;

use App\Infrastructure\ValueObject\Collection;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

/**
 * @extends Collection<RollingWindow>
 */
final class RollingWindows extends Collection
{
    public function getItemClassName(): string
    {
        return RollingWindow::class;
    }

    public static function create(
        SerializableDateTime $startDate,
        SerializableDateTime $now,
        int $windowInDays,
    ): self {
        $day = SerializableDateTime::fromString($startDate->format('Y-m-d'));
        $lastDay = SerializableDateTime::fromString($now->format('Y-m-d'));

        $rollingWindows = [];
        while ($day->isBeforeOrOn($lastDay)) {
            $rollingWindows[] = RollingWindow::endingOn($day, $windowInDays);
            $day = SerializableDateTime::fromString($day->modify('+1 day')->format('Y-m-d'));
        }

        return RollingWindows::fromArray($rollingWindows);
    }
}
