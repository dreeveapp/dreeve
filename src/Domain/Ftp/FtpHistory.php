<?php

declare(strict_types=1);

namespace App\Domain\Ftp;

use App\Domain\Activity\ActivityType;
use App\Domain\Integration\AI\SupportsAITooling;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class FtpHistory implements SupportsAITooling
{
    private const string CYCLING_KEY = 'cycling';
    private const string RUNNING_KEY = 'running';

    /** @var array<string, Ftp[]> */
    private array $ftps;

    /**
     * @param array<string, mixed> $ftps
     */
    private function __construct(
        array $ftps,
    ) {
        $this->ftps[self::CYCLING_KEY] = $this->mapFtpHistory(is_array($ftps[self::CYCLING_KEY] ?? null) ? $ftps[self::CYCLING_KEY] : [], self::CYCLING_KEY);
        $this->ftps[self::RUNNING_KEY] = $this->mapFtpHistory(is_array($ftps[self::RUNNING_KEY] ?? null) ? $ftps[self::RUNNING_KEY] : [], self::RUNNING_KEY);

        krsort($this->ftps[self::CYCLING_KEY]);
        krsort($this->ftps[self::RUNNING_KEY]);
    }

    /**
     * @param array<int|string, mixed> $entries
     *
     * @return array<int, Ftp>
     */
    private function mapFtpHistory(array $entries, string $type): array
    {
        $result = [];

        foreach ($entries as $entry) {
            $setOn = is_array($entry) && is_string($entry['on'] ?? null) ? $entry['on'] : '';
            $ftpValue = is_array($entry) ? ($entry['ftp'] ?? null) : null;

            try {
                $date = SerializableDateTime::fromString($setOn);
                $result[$date->getTimestamp()] = Ftp::fromState(
                    setOn: $date,
                    ftp: FtpValue::fromInt((int) $ftpValue)
                );
            } catch (\DateMalformedStringException) {
                throw new \InvalidArgumentException(sprintf('Invalid date "%s" set for athlete %s ftpHistory in config.yaml file', $setOn, $type));
            }
        }

        return $result;
    }

    public function findAll(ActivityType $activityType): Ftps
    {
        $ftps = match ($activityType) {
            ActivityType::RIDE => $this->ftps[self::CYCLING_KEY],
            ActivityType::RUN => $this->ftps[self::RUNNING_KEY],
            default => throw new \RuntimeException(sprintf('ActivityType "%s" does not support FTP', $activityType->value)),
        };

        // We want to sort by date in ascending order
        ksort($ftps);

        return Ftps::fromArray($ftps);
    }

    public function find(ActivityType $activityType, SerializableDateTime $on): Ftp
    {
        $on = SerializableDateTime::fromString($on->format('Y-m-d'));
        $ftps = match ($activityType) {
            ActivityType::RIDE => $this->ftps[self::CYCLING_KEY],
            ActivityType::RUN => $this->ftps[self::RUNNING_KEY],
            default => throw new \RuntimeException(sprintf('ActivityType "%s" does not support FTP', $activityType->value)),
        };

        foreach ($ftps as $ftp) {
            if ($on->isAfterOrOn($ftp->getSetOn())) {
                return $ftp;
            }
        }

        throw new EntityNotFound(sprintf('Ftp for date "%s" not found', $on));
    }

    /**
     * @return array<int, mixed>
     */
    public function exportForAITooling(): array
    {
        $history = [];
        foreach ($this->findAll(ActivityType::RIDE) as $ftp) {
            $history[] = [
                'setOn' => $ftp->getSetOn()->format('Y-m-d'),
                'ftpValue' => $ftp->getFtp()->getValue(),
            ];
        }

        return $history;
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        if (!array_key_exists(self::CYCLING_KEY, $values) && !array_key_exists(self::RUNNING_KEY, $values)) {
            // This is still an old FTP history when we didn't
            // differentiate between cycling and running yet. Make sure it's BC.
            $values[self::CYCLING_KEY] = $values;
        }

        /* @var array<string, array<string, int>> $values */
        return new self($values);
    }
}
