<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentActivityScan;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'SegmentActivityScan')]
#[ORM\Index(name: 'SegmentActivityScan_activityId', columns: ['activityId'])]
final readonly class SegmentActivityScan
{
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        private SegmentId $segmentId,
        #[ORM\Id, ORM\Column(type: 'string')]
        private ActivityId $activityId,
    ) {
    }

    public static function create(
        SegmentId $segmentId,
        ActivityId $activityId,
    ): self {
        return new self(
            segmentId: $segmentId,
            activityId: $activityId,
        );
    }

    public function getSegmentId(): SegmentId
    {
        return $this->segmentId;
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }
}
