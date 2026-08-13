<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Route\Signature;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Route\Signature\ActivityRouteSignature;
use App\Domain\Activity\Route\Signature\RouteCells;
use App\Domain\Activity\Route\Signature\RouteWaypoints;

final class ActivityRouteSignatureBuilder
{
    private ActivityId $activityId;
    private string $polylineChecksum = 'deadbeef';
    private RouteCells $cells;
    private RouteWaypoints $waypoints;

    public function __construct()
    {
        $this->activityId = ActivityId::fromUnprefixed('test');
        $this->cells = RouteCells::fromArray([1, 2, 3]);
        $this->waypoints = RouteWaypoints::fromArray([
            5101125, 300000, 5102250, 300000, 5103375, 300000, 5104500, 300000,
            5105625, 300000, 5106750, 300000, 5107875, 300000,
        ]);
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): ActivityRouteSignature
    {
        return ActivityRouteSignature::fromState(
            activityId: $this->activityId,
            polylineChecksum: $this->polylineChecksum,
            cellCount: $this->cells->count(),
            cells: $this->cells->toArray(),
            waypoints: $this->waypoints->toArray(),
        );
    }

    public function withActivityId(ActivityId $activityId): self
    {
        $this->activityId = $activityId;

        return $this;
    }

    public function withPolylineChecksum(string $polylineChecksum): self
    {
        $this->polylineChecksum = $polylineChecksum;

        return $this;
    }

    public function withCells(RouteCells $cells): self
    {
        $this->cells = $cells;

        return $this;
    }

    public function withWaypoints(RouteWaypoints $waypoints): self
    {
        $this->waypoints = $waypoints;

        return $this;
    }
}
