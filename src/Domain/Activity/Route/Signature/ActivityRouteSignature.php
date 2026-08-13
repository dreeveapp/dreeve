<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Signature;

use App\Domain\Activity\ActivityId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ActivityRouteSignature')]
final readonly class ActivityRouteSignature
{
    /**
     * @param list<int> $cells
     * @param list<int> $waypoints
     */
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        private ActivityId $activityId,
        #[ORM\Column(type: 'string', length: 8)]
        private string $polylineChecksum,
        #[ORM\Column(type: 'integer')]
        private int $cellCount,
        #[ORM\Column(type: 'blob')]
        private array $cells,
        #[ORM\Column(type: 'blob')]
        private array $waypoints,
    ) {
    }

    public static function create(
        ActivityId $activityId,
        string $polylineChecksum,
        RouteCells $cells,
        RouteWaypoints $waypoints,
    ): self {
        return new self(
            activityId: $activityId,
            polylineChecksum: $polylineChecksum,
            cellCount: $cells->count(),
            cells: $cells->toArray(),
            waypoints: $waypoints->toArray(),
        );
    }

    /**
     * @param list<int> $cells
     * @param list<int> $waypoints
     */
    public static function fromState(
        ActivityId $activityId,
        string $polylineChecksum,
        int $cellCount,
        array $cells,
        array $waypoints,
    ): self {
        return new self(
            activityId: $activityId,
            polylineChecksum: $polylineChecksum,
            cellCount: $cellCount,
            cells: $cells,
            waypoints: $waypoints,
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getPolylineChecksum(): string
    {
        return $this->polylineChecksum;
    }

    public function getCellCount(): int
    {
        return $this->cellCount;
    }

    public function getCells(): RouteCells
    {
        return RouteCells::fromArray($this->cells);
    }

    public function getWaypoints(): RouteWaypoints
    {
        return RouteWaypoints::fromArray($this->waypoints);
    }
}
