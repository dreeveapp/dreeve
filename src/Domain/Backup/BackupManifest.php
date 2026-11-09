<?php

declare(strict_types=1);

namespace App\Domain\Backup;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class BackupManifest implements \JsonSerializable
{
    private function __construct(
        private string $appVersion,
        private int $numberOfActivities,
        private ?SerializableDateTime $firstActivityStartDate,
        private ?SerializableDateTime $lastActivityStartDate,
    ) {
    }

    public static function create(
        string $appVersion,
        int $numberOfActivities,
        ?SerializableDateTime $firstActivityStartDate,
        ?SerializableDateTime $lastActivityStartDate,
    ): self {
        return new self(
            appVersion: $appVersion,
            numberOfActivities: $numberOfActivities,
            firstActivityStartDate: $firstActivityStartDate,
            lastActivityStartDate: $lastActivityStartDate,
        );
    }

    /**
     * @param array{appVersion: string, numberOfActivities: int, firstActivityStartDate: ?string, lastActivityStartDate: ?string} $manifest
     */
    public static function fromArray(array $manifest): self
    {
        return new self(
            appVersion: $manifest['appVersion'],
            numberOfActivities: $manifest['numberOfActivities'],
            firstActivityStartDate: null !== $manifest['firstActivityStartDate'] ? SerializableDateTime::fromString($manifest['firstActivityStartDate']) : null,
            lastActivityStartDate: null !== $manifest['lastActivityStartDate'] ? SerializableDateTime::fromString($manifest['lastActivityStartDate']) : null,
        );
    }

    public function getAppVersion(): string
    {
        return $this->appVersion;
    }

    public function getNumberOfActivities(): int
    {
        return $this->numberOfActivities;
    }

    public function getFirstActivityStartDate(): ?SerializableDateTime
    {
        return $this->firstActivityStartDate;
    }

    public function getLastActivityStartDate(): ?SerializableDateTime
    {
        return $this->lastActivityStartDate;
    }

    /**
     * @return array{appVersion: string, numberOfActivities: int, firstActivityStartDate: ?string, lastActivityStartDate: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'appVersion' => $this->appVersion,
            'numberOfActivities' => $this->numberOfActivities,
            'firstActivityStartDate' => $this->firstActivityStartDate?->format('Y-m-d'),
            'lastActivityStartDate' => $this->lastActivityStartDate?->format('Y-m-d'),
        ];
    }
}
