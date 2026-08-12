<?php

declare(strict_types=1);

namespace App\Domain\Milestone;

use App\Domain\Milestone\Discoverer\MilestoneDiscoverer;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class MilestoneCollector
{
    /** @var MilestoneDiscoverer[] */
    private array $discoverers;

    /**
     * @param iterable<MilestoneDiscoverer> $discoverers
     */
    public function __construct(
        #[AutowireIterator('app.milestone_discoverer')]
        iterable $discoverers,
    ) {
        $this->discoverers = iterator_to_array($discoverers);
    }

    public function discoverAll(): Milestones
    {
        $milestoneIdFactory = new MilestoneIdFactory();
        $milestones = [];

        foreach ($this->discoverers as $discoverer) {
            $milestones = array_merge($milestones, $discoverer->discover($milestoneIdFactory)->toArray());
        }

        usort($milestones, fn (Milestone $a, Milestone $b): int => $b->getAchievedOn() <=> $a->getAchievedOn());

        return Milestones::fromArray($milestones);
    }
}
