<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Activity\Eddington\EddingtonCalculator;
use App\Domain\Milestone\Context\EddingtonContext;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use App\Domain\Milestone\PreviousMilestone;
use App\Domain\Settings\SettingsRepository;

final readonly class EddingtonMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private EddingtonCalculator $eddingtonCalculator,
        private SettingsRepository $settingsRepository,
        private MilestoneIdFactory $milestoneIdFactory,
    ) {
    }

    public function discover(): Milestones
    {
        $milestones = [];
        $thresholds = range(1, 250);

        foreach ($this->eddingtonCalculator->calculate($this->settingsRepository->appearance()->getUnitSystem()) as $eddington) {
            $history = $eddington->getEddingtonHistory();
            /** @var ?Milestone $previousMilestone */
            $previousMilestone = null;

            foreach ($thresholds as $threshold) {
                if (!isset($history[$threshold])) {
                    continue;
                }

                $achievedOn = $history[$threshold];

                $previous = null;
                if ($previousMilestone) {
                    $previousContext = $previousMilestone->getContext();
                    assert($previousContext instanceof EddingtonContext);
                    $previous = PreviousMilestone::create(
                        previousMilestoneId: $previousMilestone->getId(),
                        threshold: $previousContext->getDistance(),
                        achievedOn: $previousMilestone->getAchievedOn(),
                    );
                }

                $milestone = Milestone::create(
                    id: $this->milestoneIdFactory->random(),
                    achievedOn: $achievedOn,
                    category: MilestoneCategory::EDDINGTON,
                    context: new EddingtonContext(
                        label: $eddington->getLabel(),
                        number: $threshold,
                        distance: $this->settingsRepository->appearance()->getUnitSystem()->distance($threshold),
                    ),
                )->withPrevious($previous);

                $milestones[] = $milestone;
                $previousMilestone = $milestone;
            }
        }

        return Milestones::fromArray($milestones);
    }
}
