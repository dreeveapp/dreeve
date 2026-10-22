<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Segment\SegmentId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

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

    public function getBool(string $name): ?bool
    {
        $value = $this->arguments[$name] ?? null;

        return is_bool($value) ? $value : null;
    }

    public function getDate(string $name): ?SerializableDateTime
    {
        if (null === $date = $this->getString($name)) {
            return null;
        }

        try {
            return SerializableDateTime::createFromFormat('!Y-m-d', $date);
        } catch (\InvalidArgumentException) {
            throw new ToolFailed(sprintf('"%s" must be a date in YYYY-MM-DD format.', $name));
        }
    }

    public function getSportTypes(): SportTypes
    {
        if (null === $sportTypes = $this->getString('sportType')) {
            return SportTypes::all();
        }

        return SportTypes::fromArray(array_map(
            static fn (string $sportType): SportType => SportType::tryFrom(trim($sportType))
                ?? throw new ToolFailed(sprintf('"%s" is not a known sport type.', trim($sportType))),
            explode(',', $sportTypes),
        ));
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
