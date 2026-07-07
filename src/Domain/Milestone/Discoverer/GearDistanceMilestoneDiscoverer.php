<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Milestone\Context\GearDistanceContext;
use App\Domain\Milestone\FunComparison\DistanceFunComparison;
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

final readonly class GearDistanceMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private Connection $connection,
        private SettingsRepository $settingsRepository,
        private MilestoneIdFactory $milestoneIdFactory,
    ) {
    }

    private const array METRIC_THRESHOLDS = [
        100, 250, 500, 1_000, 2_500, 5_000, 10_000, 15_000,
        20_000, 25_000, 30_000, 40_000, 50_000,
    ];

    private const array IMPERIAL_THRESHOLDS = [
        100, 250, 500, 1_000, 2_500, 5_000, 10_000, 15_000,
        20_000, 25_000, 30_000,
    ];

    public function discover(): Milestones
    {
        $rows = $this->connection->executeQuery(
            'SELECT a.startDateTime, a.gearId, a.distance, g.name as gearName
             FROM Activity a
             INNER JOIN Gear g ON a.gearId = g.gearId
             WHERE a.gearId IS NOT NULL
             ORDER BY a.startDateTime ASC'
        )->fetchAllAssociative();

        $thresholds = UnitSystem::IMPERIAL === $this->settingsRepository->appearance()->getUnitSystem() ? self::IMPERIAL_THRESHOLDS : self::METRIC_THRESHOLDS;
        $milestones = [];

        /** @var array<string, array{distanceM: float, name: string, idx: int, prev: ?Milestone}> $gearState */
        $gearState = [];

        foreach ($rows as $row) {
            $distanceM = (float) $row['distance'];
            if ($distanceM <= 0) {
                continue;
            }

            $gearId = $row['gearId'];
            $gearName = $row['gearName'];
            $achievedOn = SerializableDateTime::fromString($row['startDateTime']);

            if (!isset($gearState[$gearId])) {
                $gearState[$gearId] = [
                    'distanceM' => 0.0,
                    'name' => $gearName,
                    'idx' => 0,
                    'prev' => null,
                ];
            }

            $state = &$gearState[$gearId];
            $state['distanceM'] += $distanceM;
            $cumulativeInUnit = Meter::from($state['distanceM'])->toKilometer()->toUnitSystem($this->settingsRepository->appearance()->getUnitSystem());

            while ($state['idx'] < count($thresholds) && $cumulativeInUnit->toFloat() >= $thresholds[$state['idx']]) {
                $threshold = $thresholds[$state['idx']];
                $thresholdInUnit = $this->settingsRepository->appearance()->getUnitSystem()->distance($threshold);

                $milestone = Milestone::create(
                    id: $this->milestoneIdFactory->random(),
                    achievedOn: $achievedOn,
                    category: MilestoneCategory::GEAR_DISTANCE,
                    context: new GearDistanceContext(
                        gearName: $gearName,
                        threshold: $thresholdInUnit,
                    ),
                )
                ->withPrevious($this->buildPreviousMilestone($state['prev']))
                ->withFunComparison(DistanceFunComparison::resolve($thresholdInUnit->toMeter()->toKilometer()));

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
        assert($context instanceof GearDistanceContext);

        return PreviousMilestone::create(
            previousMilestoneId: $previous->getId(),
            threshold: $context->getThreshold(),
            achievedOn: $previous->getAchievedOn(),
        );
    }
}
