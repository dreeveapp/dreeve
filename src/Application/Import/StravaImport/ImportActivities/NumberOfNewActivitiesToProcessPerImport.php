<?php

declare(strict_types=1);

namespace App\Application\Import\StravaImport\ImportActivities;

final readonly class NumberOfNewActivitiesToProcessPerImport
{
    private function __construct(
        private int $value,
    ) {
        if ($this->value <= 0) {
            throw new \InvalidArgumentException('NumberOfNewActivitiesToProcessPerImport must be greater than 0');
        }
    }

    public static function fromInt(int $value): NumberOfNewActivitiesToProcessPerImport
    {
        return new self($value);
    }

    public function hasBeenReachedBy(int $numberOfActivitiesProcessed): bool
    {
        return $numberOfActivitiesProcessed >= $this->value;
    }
}
