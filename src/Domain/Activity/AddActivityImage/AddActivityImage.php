<?php

declare(strict_types=1);

namespace App\Domain\Activity\AddActivityImage;

use App\Domain\Activity\ActivityId;
use App\Domain\Image\ImagePath;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class AddActivityImage extends DomainCommand
{
    public function __construct(
        private ActivityId $activityId,
        private ImagePath $path,
        private string $content,
    ) {
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getPath(): ImagePath
    {
        return $this->path;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
