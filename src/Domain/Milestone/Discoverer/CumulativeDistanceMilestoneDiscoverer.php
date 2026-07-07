<?php

declare(strict_types=1);

namespace App\Domain\Milestone\Discoverer;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Milestone\Context\CumulativeDistanceContext;
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

final readonly class CumulativeDistanceMilestoneDiscoverer implements MilestoneDiscoverer
{
    public function __construct(
        private Connection $connection,
        private SettingsRepository $settingsRepository,
        private MilestoneIdFactory $milestoneIdFactory,
    ) {
    }

    private const array METRIC_THRESHOLDS = [
        100, 250, 500, 1_000, 2_500, 5_000, 10_000, 15_000,
        20_000, 25_000, 30_000, 40_000, 50_000, 75_000,
        100_000, 150_000, 200_000, 300_000, 400_000, 500_000,
    ];

    private const array IMPERIAL_THRESHOLDS = [
        100, 250, 500, 1_000, 2_500, 5_000, 10_000, 15_000,
        20_000, 25_000, 30_000, 40_000, 50_000, 75_000,
        100_000, 150_000, 200_000, 300_000,
    ];

    public function discover(): Milestones
    {
        $rows = $this->connection->executeQuery(
            'SELECT startDateTime, sportType, distance
             FROM Activity
             ORDER BY startDateTime ASC'
        )->fetchAllAssociative();

        $thresholds = UnitSystem::IMPERIAL === $this->settingsRepository->appearance()->getUnitSystem() ? self::IMPERIAL_THRESHOLDS : self::METRIC_THRESHOLDS;
        $milestones = [];
        $globalDistanceM = 0.0;
        $globalThresholdIndex = 0;
        /** @var ?Milestone $globalPreviousMilestone */
        $globalPreviousMilestone = null;

        /** @var array<string, float> $sportDistancesM */
        $sportDistancesM = [];
        /** @var array<string, int> $sportThresholdIndices */
        $sportThresholdIndices = [];
        /** @var array<string, ?Milestone> $sportPreviousMilestones */
        $sportPreviousMilestones = [];

        foreach ($rows as $row) {
            $distanceM = (float) $row['distance'];
            if ($distanceM <= 0) {
                continue;
            }

            $sportType = SportType::from($row['sportType']);
            $sportTypeValue = $row['sportType'];
            $achievedOn = SerializableDateTime::fromString($row['startDateTime']);

            $globalDistanceM += $distanceM;
            $globalCumulativeInUnit = Meter::from($globalDistanceM)->toKilometer()->toUnitSystem($this->settingsRepository->appearance()->getUnitSystem());

            while ($globalThresholdIndex < count($thresholds) && $globalCumulativeInUnit->toFloat() >= $thresholds[$globalThresholdIndex]) {
                $threshold = $thresholds[$globalThresholdIndex];
                $milestone = $this->createMilestone(
                    achievedOn: $achievedOn,
                    sportType: null,
                    threshold: $threshold,
                    previousMilestone: $globalPreviousMilestone,
                );
                $milestones[] = $milestone;
                $globalPreviousMilestone = $milestone;
                ++$globalThresholdIndex;
            }

            if (!isset($sportDistancesM[$sportTypeValue])) {
                $sportDistancesM[$sportTypeValue] = 0.0;
                $sportThresholdIndices[$sportTypeValue] = 0;
                $sportPreviousMilestones[$sportTypeValue] = null;
            }
            $sportDistancesM[$sportTypeValue] += $distanceM;
            $sportCumulativeInUnit = Meter::from($sportDistancesM[$sportTypeValue])->toKilometer()->toUnitSystem($this->settingsRepository->appearance()->getUnitSystem());

            while ($sportThresholdIndices[$sportTypeValue] < count($thresholds) && $sportCumulativeInUnit->toFloat() >= $thresholds[$sportThresholdIndices[$sportTypeValue]]) {
                $threshold = $thresholds[$sportThresholdIndices[$sportTypeValue]];
                $milestone = $this->createMilestone(
                    achievedOn: $achievedOn,
                    sportType: $sportType,
                    threshold: $threshold,
                    previousMilestone: $sportPreviousMilestones[$sportTypeValue],
                );
                $milestones[] = $milestone;
                $sportPreviousMilestones[$sportTypeValue] = $milestone;
                ++$sportThresholdIndices[$sportTypeValue];
            }
        }

        return Milestones::fromArray($milestones);
    }

    private function createMilestone(
        SerializableDateTime $achievedOn,
        ?SportType $sportType,
        int $threshold,
        ?Milestone $previousMilestone,
    ): Milestone {
        $thresholdInUnit = $this->settingsRepository->appearance()->getUnitSystem()->distance($threshold);

        return Milestone::create(
            id: $this->milestoneIdFactory->random(),
            achievedOn: $achievedOn,
            category: MilestoneCategory::CUMULATIVE_DISTANCE,
            context: new CumulativeDistanceContext(
                threshold: $thresholdInUnit,
            ),
        )
            ->withSportType($sportType)
            ->withPrevious($this->buildPreviousMilestone($previousMilestone))
            ->withFunComparison(DistanceFunComparison::resolve($thresholdInUnit->toMeter()->toKilometer()));
    }

    private function buildPreviousMilestone(?Milestone $previousMilestone): ?PreviousMilestone
    {
        if (!$previousMilestone instanceof Milestone) {
            return null;
        }

        $previousContext = $previousMilestone->getContext();
        assert($previousContext instanceof CumulativeDistanceContext);

        return PreviousMilestone::create(
            previousMilestoneId: $previousMilestone->getId(),
            threshold: $previousContext->getThreshold(),
            achievedOn: $previousMilestone->getAchievedOn(),
        );
    }
}
