<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Signature;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;

interface ActivityRouteSignatureRepository
{
    public function add(ActivityRouteSignature $activityRouteSignature): void;

    public function deleteForActivity(ActivityId $activityId): void;

    public function findActivityIdsThatNeedRouteSignatureCalculation(): ActivityIds;
}
