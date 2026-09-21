<?php

declare(strict_types=1);

namespace App\Domain\Activity\Search;

use App\Domain\Activity\Activity;
use App\Infrastructure\Repository\Item;

final readonly class ActivitySearchResult implements Item
{
    private function __construct(
        private Activity $activity,
        private bool $hasGpx,
    ) {
    }

    public static function fromState(Activity $activity, bool $hasGpx): self
    {
        return new self(
            activity: $activity,
            hasGpx: $hasGpx,
        );
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function hasGpx(): bool
    {
        return $this->hasGpx;
    }
}
