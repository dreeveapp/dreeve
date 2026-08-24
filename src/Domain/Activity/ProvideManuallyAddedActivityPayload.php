<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

trait ProvideManuallyAddedActivityPayload
{
    public const string START_DATE_TIME_FORMAT = 'Y-m-d\TH:i';

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseName(array $payload): ActivityName
    {
        if (!isset($payload['name']) || !is_string($payload['name'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "name" is required.');
        }

        $name = trim($payload['name']);
        if ('' === $name) {
            throw CouldNotDeserializeCommand::invalidPayload('The name cannot be empty.');
        }

        return ActivityName::fromString($name);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseSportType(array $payload): SportType
    {
        if (!isset($payload['sportType']) || !is_string($payload['sportType']) || !$sportType = SportType::tryFrom($payload['sportType'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A valid "sportType" is required.');
        }

        return $sportType;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseWorkoutType(array $payload): ?WorkoutType
    {
        if (!isset($payload['workoutType']) || !is_string($payload['workoutType']) || '' === trim($payload['workoutType'])) {
            return null;
        }

        if (!$workoutType = WorkoutType::tryFrom(trim($payload['workoutType']))) {
            throw CouldNotDeserializeCommand::invalidPayload('The "workoutType" is invalid.');
        }

        return $workoutType;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseStartDateTime(array $payload): SerializableDateTime
    {
        if (!isset($payload['startDateTime']) || !is_string($payload['startDateTime'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "startDateTime" is required.');
        }

        $startDateTime = trim($payload['startDateTime']);
        // The datetime-local input omits the seconds when they are zero, trim them when present.
        if (19 === strlen($startDateTime)) {
            $startDateTime = substr($startDateTime, 0, 16);
        }

        $parsed = \DateTimeImmutable::createFromFormat(self::START_DATE_TIME_FORMAT, $startDateTime);
        if (!$parsed || $parsed->format(self::START_DATE_TIME_FORMAT) !== $startDateTime) {
            throw CouldNotDeserializeCommand::invalidPayload('The "startDateTime" is invalid.');
        }

        return SerializableDateTime::createFromFormat(self::START_DATE_TIME_FORMAT, $startDateTime);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseDurationInSeconds(array $payload): int
    {
        if (!isset($payload['duration']) || !is_array($payload['duration'])) {
            throw CouldNotDeserializeCommand::invalidPayload('A "duration" is required.');
        }

        $duration = $payload['duration'];
        $parts = [];
        foreach (['hours', 'minutes', 'seconds'] as $unit) {
            $value = $duration[$unit] ?? 0;
            if ('' === $value) {
                $value = 0;
            }
            if (!is_numeric($value) || (int) $value != $value || (int) $value < 0) {
                throw CouldNotDeserializeCommand::invalidPayload('The duration must consist of positive whole numbers.');
            }
            $parts[$unit] = (int) $value;
        }

        $durationInSeconds = ($parts['hours'] * 3600) + ($parts['minutes'] * 60) + $parts['seconds'];
        if ($durationInSeconds <= 0) {
            throw CouldNotDeserializeCommand::invalidPayload('The duration must be greater than zero.');
        }

        return $durationInSeconds;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parsePositiveNumber(array $payload, string $key): float
    {
        // An empty input submits an empty string, treat it as zero.
        $value = $payload[$key] ?? 0;
        if ('' === $value) {
            return 0.0;
        }

        if (!is_numeric($value) || (float) $value < 0) {
            throw CouldNotDeserializeCommand::invalidPayload(sprintf('The "%s" must be a positive number.', $key));
        }

        return (float) $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseOptionalString(array $payload, string $key): ?string
    {
        if (!isset($payload[$key]) || !is_string($payload[$key])) {
            return null;
        }

        $value = trim($payload[$key]);

        return '' !== $value ? $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseGearId(array $payload): ?GearId
    {
        if (!$gearId = self::parseOptionalString($payload, 'gearId')) {
            return null;
        }

        return GearId::fromString($gearId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseIsCommute(array $payload): bool
    {
        return filter_var($payload['isCommute'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function parseIsGroupActivity(array $payload): bool
    {
        return filter_var($payload['isGroupActivity'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
