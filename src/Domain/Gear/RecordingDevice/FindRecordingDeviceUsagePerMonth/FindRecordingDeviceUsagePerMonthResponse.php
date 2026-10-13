<?php

declare(strict_types=1);

namespace App\Domain\Gear\RecordingDevice\FindRecordingDeviceUsagePerMonth;

use App\Domain\Gear\MonthlyUsage;
use App\Infrastructure\CQRS\Query\Response;

final readonly class FindRecordingDeviceUsagePerMonthResponse implements Response
{
    public function __construct(
        /** @var array<string, MonthlyUsage> */
        private array $usagePerDevice,
    ) {
    }

    public function getFor(string $deviceName): MonthlyUsage
    {
        return $this->usagePerDevice[$deviceName] ?? throw new \RuntimeException(sprintf('No usage found for recording device "%s"', $deviceName));
    }
}
