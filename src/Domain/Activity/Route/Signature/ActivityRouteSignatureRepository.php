<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Signature;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\DeleteActivities\DeletesActivityData;

interface ActivityRouteSignatureRepository extends DeletesActivityData
{
    public function add(ActivityRouteSignature $activityRouteSignature): void;

    public function find(ActivityId $activityId): ActivityRouteSignature;

    public function findActivityIdsThatNeedRouteSignatureCalculation(): ActivityIds;
}
