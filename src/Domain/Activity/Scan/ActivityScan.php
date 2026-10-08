<?php

declare(strict_types=1);

namespace App\Domain\Activity\Scan;

use App\Domain\Activity\ActivityId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ActivityScan')]
#[ORM\Index(name: 'ActivityScan_typeSubject', columns: ['type', 'subjectId'])]
final readonly class ActivityScan
{
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        private ActivityId $activityId,
        #[ORM\Id, ORM\Column(type: 'string', enumType: ActivityScanType::class)]
        private ActivityScanType $type,
        #[ORM\Id, ORM\Column(type: 'string', options: ['default' => ''])]
        private string $subjectId,
    ) {
    }

    public static function create(
        ActivityId $activityId,
        ActivityScanType $type,
        string $subjectId = '',
    ): self {
        return new self(
            activityId: $activityId,
            type: $type,
            subjectId: $subjectId,
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getType(): ActivityScanType
    {
        return $this->type;
    }

    public function getSubjectId(): string
    {
        return $this->subjectId;
    }
}
