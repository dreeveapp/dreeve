<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.milestone_discoverer')]
interface MilestoneDiscoverer
{
    public function discover(MilestoneIdFactory $milestoneIdFactory): Milestones;
}
