<?php

declare(strict_types=1);

namespace App\Domain\Activity\Image;

use App\Domain\Activity\DeleteActivities\DeletesActivityData;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\ValueObject\Time\Years;

interface ImageRepository extends DeletesActivityData
{
    public function findAll(): Images;

    public function count(): int;

    public function findRandomFor(SportTypes $sportTypes, Years $years): Image;
}
