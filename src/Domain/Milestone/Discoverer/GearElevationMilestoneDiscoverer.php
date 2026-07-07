<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Milestone\Context\GearElevationContext;
use App\Domain\Milestone\FunComparison\ElevationFunComparison;
use App\Domain\Milestone\Milestone;
use App\Domain\Milestone\MilestoneCategory;
use App\Domain\Milestone\MilestoneIdFactory;
use App\Domain\Milestone\Milestones;
use App\Domain\Milestone\PreviousMilestone;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class GearElevationMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private Connection $connection,
        private SettingsRepository $settingsRepository,
        private MilestoneIdFactory $milestoneIdFactory,
    ) {
    }

    private const array METRIC_THRESHOLDS = [
        500, 1_000, 2_500, 5_000, 10_000, 25_000, 50_000,
        75_000, 100_000, 150_000, 200_000,
    ];

    private const array IMPERIAL_THRESHOLDS = [
        1_000, 2_500, 5_000, 10_000, 25_000, 50_000, 75_000,
        100_000, 150_000, 200_000, 300_000, 500_000,
    ];

    public function discover(): Milestones
    {
        $rows = $this->connection->executeQuery(
            'SELECT a.startDateTime, a.gearId, a.elevation, g.name as gearName
             FROM Activity a
             INNER JOIN Gear g ON a.gearId = g.gearId
             WHERE a.gearId IS NOT NULL
             ORDER BY a.startDateTime ASC'
        )->fetchAllAssociative();

        $thresholds = UnitSystem::IMPERIAL === $this->settingsRepository->appearance()->getUnitSystem() ? self::IMPERIAL_THRESHOLDS : self::METRIC_THRESHOLDS;
        $milestones = [];

        /** @var array<string, array{elevationM: float, name: string, idx: int, prev: ?Milestone}> $gearState */
        $gearState = [];

        foreach ($rows as $row) {
            $elevationM = (float) $row['elevation'];
            if ($elevationM <= 0) {
                continue;
            }

            $gearId = $row['gearId'];
            $gearName = $row['gearName'];
            $achievedOn = SerializableDateTime::fromString($row['startDateTime']);

            if (!isset($gearState[$gearId])) {
                $gearState[$gearId] = [
                    'elevationM' => 0.0,
                    'name' => $gearName,
                    'idx' => 0,
                    'prev' => null,
                ];
            }

            $state = &$gearState[$gearId];
            $state['elevationM'] += $elevationM;
            $cumulativeInUnit = Meter::from($state['elevationM'])->toUnitSystem($this->settingsRepository->appearance()->getUnitSystem());

            while ($state['idx'] < count($thresholds) && $cumulativeInUnit->toFloat() >= $thresholds[$state['idx']]) {
                $threshold = $thresholds[$state['idx']];
                $thresholdInUnit = $this->settingsRepository->appearance()->getUnitSystem()->elevation($threshold);

                $milestone = Milestone::create(
                    id: $this->milestoneIdFactory->random(),
                    achievedOn: $achievedOn,
                    category: MilestoneCategory::GEAR_ELEVATION,
                    context: new GearElevationContext(
                        gearName: $gearName,
                        threshold: $thresholdInUnit,
                    )
                )
                ->withPrevious($this->buildPreviousMilestone($state['prev']))
                ->withFunComparison(ElevationFunComparison::resolve($thresholdInUnit->toMeter()));

                $milestones[] = $milestone;
                $state['prev'] = $milestone;
                ++$state['idx'];
            }
        }

        return Milestones::fromArray($milestones);
    }

    private function buildPreviousMilestone(?Milestone $previous): ?PreviousMilestone
    {
        if (!$previous instanceof Milestone) {
            return null;
        }

        $context = $previous->getContext();
        assert($context instanceof GearElevationContext);

        return PreviousMilestone::create(
            previousMilestoneId: $previous->getId(),
            threshold: $context->getThreshold(),
            achievedOn: $previous->getAchievedOn(),
        );
    }
}
