<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Route\ExplorerTile\ActivityExplorerTiles;
use App\Domain\Geography\ExplorerTile\ExplorerTiles;

final class ActivityExplorerTilesBuilder
{
    private ActivityId $activityId;
    private ExplorerTiles $tiles;

    public function __construct()
    {
        $this->activityId = ActivityId::fromUnprefixed('test');
        $this->tiles = ExplorerTiles::fromArray([1, 2, 3]);
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): ActivityExplorerTiles
    {
        return ActivityExplorerTiles::fromState(
            activityId: $this->activityId,
            tiles: $this->tiles->toArray(),
        );
    }

    public function withActivityId(ActivityId $activityId): self
    {
        $this->activityId = $activityId;

        return $this;
    }

    public function withTiles(ExplorerTiles $tiles): self
    {
        $this->tiles = $tiles;

        return $this;
    }
}
