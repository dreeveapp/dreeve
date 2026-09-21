<?php

declare(strict_types=1);

namespace App\Domain\Activity\Search;

use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ActivitySearchCriteria
{
    private function __construct(
        private ?SerializableDateTime $from,
        private ?SerializableDateTime $till,
        private SportTypes $sportTypes,
        private ?bool $hasGpx,
    ) {
    }

    public static function create(
        ?SerializableDateTime $from = null,
        ?SerializableDateTime $till = null,
        ?SportTypes $sportTypes = null,
        ?bool $hasGpx = null,
    ): self {
        return new self(
            from: $from,
            till: $till,
            sportTypes: $sportTypes ?? SportTypes::empty(),
            hasGpx: $hasGpx,
        );
    }

    public function getFrom(): ?SerializableDateTime
    {
        return $this->from;
    }

    public function getTill(): ?SerializableDateTime
    {
        return $this->till;
    }

    public function getSportTypes(): SportTypes
    {
        return $this->sportTypes;
    }

    public function hasGpx(): ?bool
    {
        return $this->hasGpx;
    }
}
