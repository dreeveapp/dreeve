<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentId;

final readonly class ToolArguments
{
    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __construct(
        private array $arguments,
    ) {
    }

    public function getString(string $name): ?string
    {
        $value = $this->arguments[$name] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function getInt(string $name): ?int
    {
        $value = $this->arguments[$name] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    public function getActivityId(): ActivityId
    {
        return ActivityId::fromPrefixedOrUnprefixed((string) $this->getString('activityId'));
    }

    public function getSegmentId(): SegmentId
    {
        return SegmentId::fromPrefixedOrUnprefixed((string) $this->getString('segmentId'));
    }
}
