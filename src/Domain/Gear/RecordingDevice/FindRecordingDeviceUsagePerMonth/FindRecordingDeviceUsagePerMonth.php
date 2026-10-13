<?php

declare(strict_types=1);

namespace App\Domain\Gear\RecordingDevice\FindRecordingDeviceUsagePerMonth;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Gear\RecordingDevice\FindRecordingDeviceUsagePerMonth\FindRecordingDeviceUsagePerMonthResponse>
 */
final readonly class FindRecordingDeviceUsagePerMonth implements Query
{
}
