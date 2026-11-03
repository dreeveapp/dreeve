<?php

declare(strict_types=1);

namespace App\Domain\Activity\DeleteActivities;

use App\Domain\Activity\ActivityId;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.activity.deletes_activity_data')]
interface DeletesActivityData
{
    public function deleteForActivity(ActivityId $activityId): void;
}
