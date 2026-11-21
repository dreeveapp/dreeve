<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use App\Domain\Activity\Activity;
use App\Infrastructure\Doctrine\Migrations\ActivityFileRefreshMigration;

final class ActivityFileRefreshMigrationStub extends ActivityFileRefreshMigration
{
    protected function columnsToRefresh(Activity $parsedActivity): array
    {
        return [
            'movingTimeInSeconds' => $parsedActivity->getMovingTimeInSeconds(),
            'elevation' => $parsedActivity->getElevation()->toInt(),
        ];
    }
}
