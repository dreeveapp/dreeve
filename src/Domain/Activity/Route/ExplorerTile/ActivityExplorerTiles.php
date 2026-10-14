<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ActivityExplorerTiles')]
final readonly class ActivityExplorerTiles
{
    /**
     * @param list<int> $tiles
     */
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        private ActivityId $activityId,
        #[ORM\Column(type: 'integer')]
        private int $tileCount,
        #[ORM\Column(type: 'blob')]
        private array $tiles,
    ) {
    }

    public static function create(
        ActivityId $activityId,
        ExplorerTiles $tiles,
    ): self {
        return new self(
            activityId: $activityId,
            tileCount: $tiles->count(),
            tiles: $tiles->toArray(),
        );
    }

    /**
     * @param list<int> $tiles
     */
    public static function fromState(
        ActivityId $activityId,
        int $tileCount,
        array $tiles,
    ): self {
        return new self(
            activityId: $activityId,
            tileCount: $tileCount,
            tiles: $tiles,
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getTileCount(): int
    {
        return $this->tileCount;
    }

    public function getTiles(): ExplorerTiles
    {
        return ExplorerTiles::fromArray($this->tiles);
    }
}
