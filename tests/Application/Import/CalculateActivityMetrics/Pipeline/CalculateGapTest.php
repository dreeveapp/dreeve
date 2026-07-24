<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateGap;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Gap\GapSegment;
use App\Domain\Activity\Split\ActivitySplitRepository;
use App\Domain\Activity\Split\ActivitySplits;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Split\ActivitySplitBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\SpyOutput;

class CalculateGapTest extends ContainerTestCase
{
    private CalculateGap $calculateGap;
    private ActivitySplitRepository $activitySplitRepository;
    private ActivityStreamRepository $activityStreamRepository;
    private ActivityRepository $activityRepository;

    public function testProcessCalculatesGapForRunActivity(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-1');
        $this->addActivity($activityId, SportType::RUN);
        $this->addStreams($activityId, $this->buildHillyTrackPoints());
        $this->addMetricSplits($activityId, [1000.0, 1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $metricSplits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC);
        $this->assertNotNull($metricSplits->toArray()[0]->getGapPaceInSecondsPerKm());
        $this->assertNotNull($metricSplits->toArray()[1]->getGapPaceInSecondsPerKm());
    }

    public function testProcessCalculatesGapForTrailRunActivity(): void
    {
        $activityId = ActivityId::fromUnprefixed('trail-run-1');
        $this->addActivity($activityId, SportType::TRAIL_RUN);
        $this->addStreams($activityId, $this->buildHillyTrackPoints());
        $this->addMetricSplits($activityId, [1000.0, 1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $metricSplits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC);
        $this->assertNotNull($metricSplits->toArray()[0]->getGapPaceInSecondsPerKm());
    }

    public function testProcessSkipsActivitiesAlreadyWithGap(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-already-done');
        $this->addActivity($activityId, SportType::RUN);
        $this->addStreams($activityId, $this->buildHillyTrackPoints());

        $existingGap = SecPerKm::from(999.0);
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withSplitNumber(1)
                ->withDistanceInMeter(1000)
                ->withGapPace($existingGap)
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $metricSplits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC);
        $this->assertEqualsWithDelta(
            999.0,
            $metricSplits->toArray()[0]->getGapPaceInSecondsPerKm()->toFloat(),
            0.01,
        );
    }

    public function testProcessSkipsNonRunActivity(): void
    {
        $activityId = ActivityId::fromUnprefixed('ride-1');
        $this->addActivity($activityId, SportType::RIDE);
        $this->addStreams($activityId, $this->buildHillyTrackPoints());
        $this->addMetricSplits($activityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $metricSplits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC);
        $this->assertNull($metricSplits->toArray()[0]->getGapPaceInSecondsPerKm());
    }

    public function testProcessSkipsActivityWithMissingStreams(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-no-streams');
        $this->addActivity($activityId, SportType::RUN);
        $this->addMetricSplits($activityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $metricSplits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC);
        $this->assertNull($metricSplits->toArray()[0]->getGapPaceInSecondsPerKm());
    }

    public function testProcessSkipsActivityWithPartialStreams(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-partial');
        $this->addActivity($activityId, SportType::RUN);

        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::LAT_LNG)
                ->withData([[50.0, 4.0], [50.001, 4.001]])
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::TIME)
                ->withData([0, 60])
                ->build()
        );
        // Missing ALTITUDE stream.

        $this->addMetricSplits($activityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $metricSplits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC);
        $this->assertNull($metricSplits->toArray()[0]->getGapPaceInSecondsPerKm());
    }

    public function testProcessSkipsMalformedCoordinateItems(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-malformed-coordinates');
        $this->addActivity($activityId, SportType::RUN);
        $this->addRawStreams(
            activityId: $activityId,
            latLng: ['invalid', [50.0], [50.0, 4.0], [50.009, 4.0]],
            altitude: [100.0, 100.0, 100.0, 100.0],
            time: [0, 5, 10, 20],
        );
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withDistanceInMeter(1000.0)
                ->withAverageSpeed(MetersPerSecond::from(1000.0 / 10.0))
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($split->getGapPaceInSecondsPerKm());
    }

    public function testProcessUsesShortestAvailableStreamLength(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-mismatched-stream-lengths');
        $this->addActivity($activityId, SportType::RUN);
        $this->addRawStreams(
            activityId: $activityId,
            latLng: [[50.0, 4.0], [50.009, 4.0], [50.018, 4.0]],
            altitude: [100.0, 100.0],
            time: [0, 10],
        );
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withDistanceInMeter(1000.0)
                ->withAverageSpeed(MetersPerSecond::from(1000.0 / 10.0))
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($split->getGapPaceInSecondsPerKm());
    }

    public function testProcessTreatsMissingMovingEntriesAsNonMoving(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-short-moving-stream');
        $this->addActivity($activityId, SportType::RUN);
        $this->addRawStreams(
            activityId: $activityId,
            latLng: [[50.0, 4.0], [50.009, 4.0], [50.018, 4.0]],
            altitude: [100.0, 100.0, 100.0],
            time: [0, 10, 20],
            moving: [true],
        );
        $this->addMetricSplits($activityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNull($split->getGapPaceInSecondsPerKm());
        $this->assertStringContainsString('Calculated GAP for 0 activities', (string) $output);
    }

    public function testProcessSkipsWhenAllCoordinatesAreMalformed(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-all-malformed-coordinates');
        $this->addActivity($activityId, SportType::RUN);
        $this->addRawStreams(
            activityId: $activityId,
            latLng: ['invalid', [50.0], [50.0, 4.0, 1.0]],
            altitude: [100.0, 100.0, 100.0],
            time: [0, 10, 20],
        );
        $this->addMetricSplits($activityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNull($split->getGapPaceInSecondsPerKm());
        $this->assertStringContainsString('Calculated GAP for 0 activities', (string) $output);
    }

    public function testProcessSkipsWhenMovingStreamFiltersOutAllPoints(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-all-non-moving');
        $this->addActivity($activityId, SportType::RUN);
        $this->addRawStreams(
            activityId: $activityId,
            latLng: [[50.0, 4.0], [50.009, 4.0], [50.018, 4.0]],
            altitude: [100.0, 100.0, 100.0],
            time: [0, 10, 20],
            moving: [false, false, false],
        );
        $this->addMetricSplits($activityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNull($split->getGapPaceInSecondsPerKm());
        $this->assertStringContainsString('Calculated GAP for 0 activities', (string) $output);
    }

    public function testProcessCalculatesGapWhenMovingStreamKeepsLastTwoPoints(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-moving-keeps-last-two');
        $this->addActivity($activityId, SportType::RUN);
        $this->addRawStreams(
            activityId: $activityId,
            latLng: [[50.0, 4.0], [50.009, 4.0], [50.018, 4.0]],
            altitude: [100.0, 100.0, 100.0],
            time: [0, 10, 20],
            moving: [false, true, true],
        );
        $this->addMetricSplitWithSpeed($activityId, 1000.0, 1000.0 / 10.0);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($split->getGapPaceInSecondsPerKm());
        $this->assertStringContainsString('Calculated GAP for 1 activities', (string) $output);
    }

    public function testProcessCalculatesGapWhenMovingStreamKeepsAllPoints(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-all-moving');
        $this->addActivity($activityId, SportType::RUN);
        $this->addRawStreams(
            activityId: $activityId,
            latLng: [[50.0, 4.0], [50.009, 4.0]],
            altitude: [100.0, 100.0],
            time: [0, 10],
            moving: [true, true],
        );
        $this->addMetricSplitWithSpeed($activityId, 1000.0, 1000.0 / 10.0);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($split->getGapPaceInSecondsPerKm());
        $this->assertStringContainsString('Calculated GAP for 1 activities', (string) $output);
    }

    public function testProcessSkipsWhenShortestStreamLeavesFewerThanTwoPoints(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-shortest-stream-single-point');
        $this->addActivity($activityId, SportType::RUN);
        $this->addRawStreams(
            activityId: $activityId,
            latLng: [[50.0, 4.0], [50.009, 4.0], [50.018, 4.0]],
            altitude: [100.0],
            time: [0, 10, 20],
        );
        $this->addMetricSplits($activityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNull($split->getGapPaceInSecondsPerKm());
        $this->assertStringContainsString('Calculated GAP for 0 activities', (string) $output);
    }

    public function testProcessSkipsActivityWhenNoSegmentsAreGenerated(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-no-gap-segments');
        $this->addActivity($activityId, SportType::RUN);
        $this->addRawStreams(
            activityId: $activityId,
            latLng: [[50.0, 4.0], [50.0, 4.0], [50.009, 4.0]],
            altitude: [100.0, 100.0, 100.0],
            time: [0, 10, 10],
        );
        $this->addMetricSplits($activityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNull($split->getGapPaceInSecondsPerKm());
        $this->assertStringContainsString('Calculated GAP for 0 activities', (string) $output);
    }

    public function testProcessUpdatesImperialSplitsWhenMetricSplitsAreMissing(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-imperial-only');
        $this->addActivity($activityId, SportType::RUN);
        $this->addStreams($activityId, $this->buildFlatTrackPoints());
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::IMPERIAL)
                ->withSplitNumber(1)
                ->withDistanceInMeter(1609.34)
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $imperialSplit = $this->activitySplitRepository->findBy($activityId, UnitSystem::IMPERIAL)->toArray()[0];
        $this->assertNotNull($imperialSplit->getGapPaceInSecondsPerKm());
        $this->assertSame([], $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray());
    }

    public function testProcessUpdatesMetricAndImperialSplits(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-both-units');
        $this->addActivity($activityId, SportType::RUN);
        $this->addStreams($activityId, $this->buildHillyTrackPoints());
        $this->addMetricSplits($activityId, [1000.0]);

        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::IMPERIAL)
                ->withSplitNumber(1)
                ->withDistanceInMeter(1609.34)
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $metricSplits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC);
        $imperialSplits = $this->activitySplitRepository->findBy($activityId, UnitSystem::IMPERIAL);
        $this->assertNotNull($metricSplits->toArray()[0]->getGapPaceInSecondsPerKm());
        $this->assertNotNull($imperialSplits->toArray()[0]->getGapPaceInSecondsPerKm());
        $this->assertCount(1, $metricSplits->toArray());
        $this->assertCount(1, $imperialSplits->toArray());
    }

    public function testProcessHandlesMultipleActivitiesInSingleRun(): void
    {
        $activityIdOne = ActivityId::fromUnprefixed('run-batch-1');
        $activityIdTwo = ActivityId::fromUnprefixed('run-batch-2');
        $this->addActivity($activityIdOne, SportType::RUN);
        $this->addActivity($activityIdTwo, SportType::RUN);
        $this->addStreams($activityIdOne, $this->buildHillyTrackPoints());
        $this->addStreams($activityIdTwo, $this->buildHillyTrackPoints());
        $this->addMetricSplits($activityIdOne, [1000.0]);
        $this->addMetricSplits($activityIdTwo, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $splitsOne = $this->activitySplitRepository->findBy($activityIdOne, UnitSystem::METRIC);
        $splitsTwo = $this->activitySplitRepository->findBy($activityIdTwo, UnitSystem::METRIC);
        $this->assertNotNull($splitsOne->toArray()[0]->getGapPaceInSecondsPerKm());
        $this->assertNotNull($splitsTwo->toArray()[0]->getGapPaceInSecondsPerKm());
        $this->assertStringContainsString('Calculated GAP for 2 activities', (string) $output);
    }

    public function testProcessIsIdempotent(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-idempotent');
        $this->addActivity($activityId, SportType::RUN);
        $this->addStreams($activityId, $this->buildHillyTrackPoints());
        $this->addMetricSplits($activityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);
        $gapAfterFirstRun = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)
            ->toArray()[0]->getGapPaceInSecondsPerKm();

        $this->calculateGap->process($output);
        $gapAfterSecondRun = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)
            ->toArray()[0]->getGapPaceInSecondsPerKm();

        $this->assertNotNull($gapAfterFirstRun);
        $this->assertEqualsWithDelta($gapAfterFirstRun->toFloat(), $gapAfterSecondRun->toFloat(), 0.01);
    }

    public function testProcessFiltersOutNonMovingPoints(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-moving-filter');
        $this->addActivity($activityId, SportType::RUN);

        $latLng = [];
        $altitude = [];
        $time = [];
        $moving = [];
        for ($i = 0; $i < 100; ++$i) {
            $latLng[] = [50.0 + $i * 0.0001, 4.0 + $i * 0.0001];
            $altitude[] = 100.0 + $i * 0.5;
            $time[] = $i * 5;
            $moving[] = 0 === $i % 2;
        }

        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::LAT_LNG)
                ->withData($latLng)
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::ALTITUDE)
                ->withData($altitude)
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::TIME)
                ->withData($time)
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::MOVING)
                ->withData($moving)
                ->build()
        );

        $this->addMetricSplits($activityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $splits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC);
        $this->assertNotNull($splits->toArray()[0]->getGapPaceInSecondsPerKm());
    }

    public function testProcessPreservesMovingDurationForRepeatedCoordinates(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-repeated-coordinates');
        $this->addActivity($activityId, SportType::RUN);

        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::LAT_LNG)
                ->withData([
                    [51.0000, 4.0000],
                    [51.0000, 4.0000],
                    [51.0000, 4.0000],
                    [51.0009, 4.0000],
                ])
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::ALTITUDE)
                ->withData([10.0, 10.0, 10.0, 10.0])
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::TIME)
                ->withData([0, 10, 20, 30])
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::MOVING)
                ->withData([true, true, true, true])
                ->build()
        );

        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withDistanceInMeter(100.0)
                ->withAverageSpeed(MetersPerSecond::from(3.333))
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $gapPace = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)
            ->toArray()[0]->getGapPaceInSecondsPerKm();

        $this->assertNotNull($gapPace);
        $this->assertGreaterThan(250.0, $gapPace->toFloat());
        $this->assertLessThan(350.0, $gapPace->toFloat());
    }

    public function testProcessIgnoresGradeStreamWhenAvailable(): void
    {
        $withGradeActivityId = ActivityId::fromUnprefixed('run-grade-stream');
        $withoutGradeActivityId = ActivityId::fromUnprefixed('run-no-grade-stream');
        $this->addActivity($withGradeActivityId, SportType::RUN);
        $this->addActivity($withoutGradeActivityId, SportType::RUN);

        $trackPoints = $this->buildFlatTrackPoints();
        $this->addStreams($withGradeActivityId, $trackPoints);
        $this->addStreams($withoutGradeActivityId, $trackPoints);
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($withGradeActivityId)
                ->withStreamType(StreamType::GRADE)
                ->withData(array_fill(0, count($trackPoints['time']), 50.0))
                ->build()
        );
        $this->addMetricSplits($withGradeActivityId, [1000.0]);
        $this->addMetricSplits($withoutGradeActivityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $splitWithGrade = $this->activitySplitRepository->findBy($withGradeActivityId, UnitSystem::METRIC)->toArray()[0];
        $splitWithoutGrade = $this->activitySplitRepository->findBy($withoutGradeActivityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($splitWithGrade->getGapPaceInSecondsPerKm());
        $this->assertNotNull($splitWithoutGrade->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(
            $splitWithoutGrade->getGapPaceInSecondsPerKm()->toFloat(),
            $splitWithGrade->getGapPaceInSecondsPerKm()->toFloat(),
            0.01,
            'The Strava grade stream is intentionally ignored for split GAP.',
        );
    }

    public function testProcessKeepsExactlyFlatSplitAtActualPace(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-exact-flat');
        $this->addActivity($activityId, SportType::RUN);

        $trackPoints = $this->buildFlatTrackPoints();
        $this->addStreams($activityId, $trackPoints);
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withSplitNumber(1)
                ->withDistanceInMeter(1000.0)
                ->withAverageSpeed(MetersPerSecond::from(1000.0 / 995.0))
                ->withElevationDifferenceInMeter(0)
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($split->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(
            $split->getPaceInSecPerKm()->toFloat(),
            $split->getGapPaceInSecondsPerKm()->toFloat(),
            0.01,
            'Exactly flat splits should keep GAP equal to actual pace.',
        );
    }

    public function testProcessKeepsAltitudeDerivedFlatGapCloseToActualPace(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-altitude-flat');
        $this->addActivity($activityId, SportType::RUN);

        $trackPoints = $this->buildFlatTrackPoints();
        $this->addStreams($activityId, $trackPoints);
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withSplitNumber(1)
                ->withDistanceInMeter(1000.0)
                ->withAverageSpeed(MetersPerSecond::from(1000.0 / 995.0))
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($split->getGapPaceInSecondsPerKm());
        $this->assertLessThan(
            8.0,
            abs($split->getGapPaceInSecondsPerKm()->toFloat() - $split->getPaceInSecPerKm()->toFloat()),
            'Flat altitude-derived GAP should stay close to actual pace when split pace matches stream timing.',
        );
    }

    public function testProcessUsesSegmentGapForRollingTerrainWithFlatNetElevation(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-rolling-net-flat');
        $this->addActivity($activityId, SportType::RUN);

        $this->addStreams($activityId, $this->buildRollingNetFlatTrackPoints());
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withSplitNumber(1)
                ->withDistanceInMeter(1000.0)
                ->withAverageSpeed(MetersPerSecond::from(1000.0 / 995.0))
                ->withElevationDifferenceInMeter(0)
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($split->getGapPaceInSecondsPerKm());
        $this->assertGreaterThan(
            10.0,
            abs($split->getGapPaceInSecondsPerKm()->toFloat() - $split->getPaceInSecPerKm()->toFloat()),
            'Rolling terrain should keep segment-derived GAP even when split net elevation is flat.',
        );
    }

    public function testProcessAppliesSteepDownhillRebound(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-steep-downhill');
        $this->addActivity($activityId, SportType::RUN);

        $this->addStreams($activityId, $this->buildLinearGradeTrackPoints(-0.30));
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withSplitNumber(1)
                ->withDistanceInMeter(1000.0)
                ->withAverageSpeed(MetersPerSecond::from(1000.0 / 995.0))
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($split->getGapPaceInSecondsPerKm());
        $this->assertLessThan(
            $split->getPaceInSecPerKm()->toFloat(),
            $split->getGapPaceInSecondsPerKm()->toFloat(),
            'The Strava-like model should rebound on very steep downhill grades.',
        );
    }

    public function testProcessClampsAbsurdlyFastCalculatedGap(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-gap-clamp-fast');
        $this->addActivity($activityId, SportType::RUN);

        $this->addStreams($activityId, $this->buildLinearGradeTrackPoints(0.45, 1));
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withSplitNumber(1)
                ->withDistanceInMeter(1000.0)
                ->withAverageSpeed(MetersPerSecond::from(3.0))
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($split->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(
            $split->getPaceInSecPerKm()->toFloat() * 0.5,
            $split->getGapPaceInSecondsPerKm()->toFloat(),
            0.01,
            'Absurdly fast calculated GAP should be clamped to 50% of actual pace.',
        );
    }

    public function testProcessClampsAbsurdlySlowCalculatedGap(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-gap-clamp-slow');
        $this->addActivity($activityId, SportType::RUN);

        $this->addStreams($activityId, $this->buildLinearGradeTrackPoints(-0.50, 1));
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withSplitNumber(1)
                ->withDistanceInMeter(1000.0)
                ->withAverageSpeed(MetersPerSecond::from(20.0))
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($split->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(
            $split->getPaceInSecPerKm()->toFloat() * 1.6,
            $split->getGapPaceInSecondsPerKm()->toFloat(),
            0.01,
            'Absurdly slow calculated GAP should be clamped to 160% of actual pace.',
        );
    }

    public function testProcessSkipsZeroDistanceSplitCollection(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-zero-distance-split');
        $this->addActivity($activityId, SportType::RUN);
        $this->addStreams($activityId, $this->buildFlatTrackPoints());
        $this->addMetricSplits($activityId, [0.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNull($split->getGapPaceInSecondsPerKm());
    }

    public function testMapSegmentsToSplitsReturnsEmptyArrayForEmptySplitCollection(): void
    {
        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke($this->calculateGap, [$this->flatGapSegment(1000.0, 250)], ActivitySplits::empty());

        $this->assertSame([], $mappedSplits);
    }

    public function testMapSegmentsToSplitsLeavesZeroDistanceSplitsUnchanged(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(0.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke($this->calculateGap, [$this->flatGapSegment(1000.0, 250)], ActivitySplits::fromArray([$split]));

        $this->assertSame($split, $mappedSplits[0]);
        $this->assertNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
    }

    public function testMapSegmentsToSplitsLeavesSplitUnchangedWhenTotalSegmentDistanceIsZero(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();
        $segment = GapSegment::create(
            distanceInMeters: 0.0,
            durationInSeconds: 250,
            grade: 0.0,
            gapMultiplier: 1.0,
        );

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke($this->calculateGap, [$segment], ActivitySplits::fromArray([$split]));

        $this->assertSame($split, $mappedSplits[0]);
        $this->assertNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
    }

    public function testMapSegmentsToSplitsAdvancesPastZeroDistanceSplitAndMapsNextSplit(): void
    {
        $zeroDistanceSplit = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(0.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();
        $normalSplit = ActivitySplitBuilder::fromDefaults()
            ->withSplitNumber(2)
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke(
            $this->calculateGap,
            [$this->flatGapSegment(1000.0, 250)],
            ActivitySplits::fromArray([$zeroDistanceSplit, $normalSplit]),
        );

        $this->assertNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
        $this->assertNotNull($mappedSplits[1]->getGapPaceInSecondsPerKm());
    }

    public function testMapSegmentsToSplitsTreatsDistanceWithinToleranceAsComplete(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke($this->calculateGap, [$this->flatGapSegment(999.999995, 250)], ActivitySplits::fromArray([$split]));

        $this->assertNotNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(250.0, $mappedSplits[0]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
    }

    public function testMapSegmentsToSplitsScalesPartialGpsDistanceToCompleteFinalSplit(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(2.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke($this->calculateGap, [$this->flatGapSegment(500.0, 125)], ActivitySplits::fromArray([$split]));

        $this->assertNotNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
    }

    public function testMapSegmentsToSplitsFallsBackToActualPaceForZeroDurationSegment(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();
        $segment = GapSegment::create(
            distanceInMeters: 1000.0,
            durationInSeconds: 0,
            grade: 0.0,
            gapMultiplier: 1.0,
        );

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke($this->calculateGap, [$segment], ActivitySplits::fromArray([$split]));

        $this->assertNotNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta($split->getPaceInSecPerKm()->toFloat(), $mappedSplits[0]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
    }

    public function testMapSegmentsToSplitsLeavesSplitUnchangedWhenMultiplierProducesZeroAdjustedDistance(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();
        $segment = GapSegment::create(
            distanceInMeters: 1000.0,
            durationInSeconds: 250,
            grade: 0.0,
            gapMultiplier: 0.0,
        );

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke($this->calculateGap, [$segment], ActivitySplits::fromArray([$split]));

        $this->assertSame($split, $mappedSplits[0]);
        $this->assertNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
    }

    public function testMapSegmentsToSplitsLeavesTrailingZeroDistanceSplitUnchangedAfterMappingPreviousSplit(): void
    {
        $normalSplit = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();
        $zeroDistanceSplit = ActivitySplitBuilder::fromDefaults()
            ->withSplitNumber(2)
            ->withDistanceInMeter(0.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke(
            $this->calculateGap,
            [$this->flatGapSegment(1000.0, 250)],
            ActivitySplits::fromArray([$normalSplit, $zeroDistanceSplit]),
        );

        $this->assertNotNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
        $this->assertNull($mappedSplits[1]->getGapPaceInSecondsPerKm());
    }

    public function testMapSegmentsToSplitsSplitsOneSegmentAcrossTwoSplitsProportionally(): void
    {
        $firstSplit = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(400.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();
        $secondSplit = ActivitySplitBuilder::fromDefaults()
            ->withSplitNumber(2)
            ->withDistanceInMeter(600.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke(
            $this->calculateGap,
            [$this->flatGapSegment(1000.0, 300)],
            ActivitySplits::fromArray([$firstSplit, $secondSplit]),
        );

        $this->assertNotNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
        $this->assertNotNull($mappedSplits[1]->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(300.0, $mappedSplits[0]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
        $this->assertEqualsWithDelta(300.0, $mappedSplits[1]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
    }

    public function testMapSegmentsToSplitsCarriesRemainingSegmentDistanceIntoNextSplit(): void
    {
        $firstSplit = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(800.0)
            ->withAverageSpeed(MetersPerSecond::from(2.0))
            ->build();
        $secondSplit = ActivitySplitBuilder::fromDefaults()
            ->withSplitNumber(2)
            ->withDistanceInMeter(200.0)
            ->withAverageSpeed(MetersPerSecond::from(2.0))
            ->build();
        $segments = [
            $this->flatGapSegment(600.0, 120, 1.0),
            $this->flatGapSegment(400.0, 160, 0.5),
        ];

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke(
            $this->calculateGap,
            $segments,
            ActivitySplits::fromArray([$firstSplit, $secondSplit]),
        );

        $this->assertNotNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
        $this->assertNotNull($mappedSplits[1]->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(285.71, $mappedSplits[0]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
        $this->assertEqualsWithDelta(800.0, $mappedSplits[1]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
    }

    public function testMapSegmentsToSplitsScalesGpsDistanceAcrossMultipleSplitsWithoutScalingDuration(): void
    {
        $firstSplit = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(400.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();
        $secondSplit = ActivitySplitBuilder::fromDefaults()
            ->withSplitNumber(2)
            ->withDistanceInMeter(600.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke(
            $this->calculateGap,
            [$this->flatGapSegment(500.0, 250)],
            ActivitySplits::fromArray([$firstSplit, $secondSplit]),
        );

        $this->assertNotNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
        $this->assertNotNull($mappedSplits[1]->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(250.0, $mappedSplits[0]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
        $this->assertEqualsWithDelta(250.0, $mappedSplits[1]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
    }

    public function testMapSegmentsToSplitsScalesShortGpsDistanceToSplitDistance(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke($this->calculateGap, [$this->flatGapSegment(500.0, 250)], ActivitySplits::fromArray([$split]));

        $this->assertNotNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(250.0, $mappedSplits[0]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
    }

    public function testMapSegmentsToSplitsScalesLongGpsDistanceToSplitDistance(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(2.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke($this->calculateGap, [$this->flatGapSegment(2000.0, 500)], ActivitySplits::fromArray([$split]));

        $this->assertCount(1, $mappedSplits);
        $this->assertNotNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(500.0, $mappedSplits[0]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
    }

    public function testMapSegmentsToSplitsCombinesSegmentPortionsWithDifferentMultipliers(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();
        $segments = [
            $this->flatGapSegment(500.0, 100, 2.0),
            $this->flatGapSegment(500.0, 200, 1.0),
        ];

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke($this->calculateGap, $segments, ActivitySplits::fromArray([$split]));

        $this->assertNotNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(200.0, $mappedSplits[0]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
    }

    public function testFinalizeSplitGapAddsGapForCompleteSplitWithAdjustedDistance(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'finalizeSplitGap');
        $finalizedSplit = $method->invoke($this->calculateGap, $split, 1000.0, 1000.0, 200.0, 800.0);

        $this->assertNotSame($split, $finalizedSplit);
        $this->assertNotNull($finalizedSplit->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(250.0, $finalizedSplit->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
    }

    public function testFinalizeSplitGapLeavesZeroAdjustedDistanceSplitUnchanged(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'finalizeSplitGap');
        $finalizedSplit = $method->invoke($this->calculateGap, $split, 1000.0, 1000.0, 250.0, 0.0);

        $this->assertSame($split, $finalizedSplit);
        $this->assertNull($finalizedSplit->getGapPaceInSecondsPerKm());
    }

    public function testResolveGapPaceFallsBackToActualPaceForInvalidCalculatedGap(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'resolveGapPace');
        $gapPace = $method->invoke($this->calculateGap, $split, INF, 1000.0);

        $this->assertInstanceOf(SecPerKm::class, $gapPace);
        $this->assertEqualsWithDelta(
            $split->getPaceInSecPerKm()->toFloat(),
            $gapPace->toFloat(),
            0.01,
        );
    }

    public function testResolveGapPaceFallsBackToActualPaceForNanCalculatedGap(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'resolveGapPace');
        $gapPace = $method->invoke($this->calculateGap, $split, NAN, 1000.0);

        $this->assertInstanceOf(SecPerKm::class, $gapPace);
        $this->assertEqualsWithDelta(
            $split->getPaceInSecPerKm()->toFloat(),
            $gapPace->toFloat(),
            0.01,
        );
    }

    public function testResolveGapPaceFallsBackToActualPaceForZeroMappedDistance(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'resolveGapPace');
        $gapPace = $method->invoke($this->calculateGap, $split, 200.0, 0.0);

        $this->assertInstanceOf(SecPerKm::class, $gapPace);
        $this->assertEqualsWithDelta(
            $split->getPaceInSecPerKm()->toFloat(),
            $gapPace->toFloat(),
            0.01,
        );
    }

    public function testResolveGapPacePreservesCalculatedGapInsideBounds(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'resolveGapPace');
        $gapPace = $method->invoke($this->calculateGap, $split, 240.0, 1000.0);

        $this->assertInstanceOf(SecPerKm::class, $gapPace);
        $this->assertEqualsWithDelta(240.0, $gapPace->toFloat(), 0.01);
    }

    public function testResolveGapPaceFallsBackToActualPaceForNonPositiveCalculatedGap(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'resolveGapPace');
        $zeroGapPace = $method->invoke($this->calculateGap, $split, 0.0, 1000.0);
        $negativeGapPace = $method->invoke($this->calculateGap, $split, -1.0, 1000.0);

        $this->assertInstanceOf(SecPerKm::class, $zeroGapPace);
        $this->assertInstanceOf(SecPerKm::class, $negativeGapPace);
        $this->assertEqualsWithDelta($split->getPaceInSecPerKm()->toFloat(), $zeroGapPace->toFloat(), 0.01);
        $this->assertEqualsWithDelta($split->getPaceInSecPerKm()->toFloat(), $negativeGapPace->toFloat(), 0.01);
    }

    public function testResolveGapPacePreservesExactClampBoundaries(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'resolveGapPace');
        $lowerBoundaryGapPace = $method->invoke($this->calculateGap, $split, $split->getPaceInSecPerKm()->toFloat() * 0.5, 1000.0);
        $upperBoundaryGapPace = $method->invoke($this->calculateGap, $split, $split->getPaceInSecPerKm()->toFloat() * 1.6, 1000.0);

        $this->assertInstanceOf(SecPerKm::class, $lowerBoundaryGapPace);
        $this->assertInstanceOf(SecPerKm::class, $upperBoundaryGapPace);
        $this->assertEqualsWithDelta($split->getPaceInSecPerKm()->toFloat() * 0.5, $lowerBoundaryGapPace->toFloat(), 0.01);
        $this->assertEqualsWithDelta($split->getPaceInSecPerKm()->toFloat() * 1.6, $upperBoundaryGapPace->toFloat(), 0.01);
    }

    public function testResolveGapPaceClampsValuesOutsideBounds(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'resolveGapPace');
        $belowLowerBoundaryGapPace = $method->invoke($this->calculateGap, $split, $split->getPaceInSecPerKm()->toFloat() * 0.49, 1000.0);
        $aboveUpperBoundaryGapPace = $method->invoke($this->calculateGap, $split, $split->getPaceInSecPerKm()->toFloat() * 1.61, 1000.0);

        $this->assertInstanceOf(SecPerKm::class, $belowLowerBoundaryGapPace);
        $this->assertInstanceOf(SecPerKm::class, $aboveUpperBoundaryGapPace);
        $this->assertEqualsWithDelta($split->getPaceInSecPerKm()->toFloat() * 0.5, $belowLowerBoundaryGapPace->toFloat(), 0.01);
        $this->assertEqualsWithDelta($split->getPaceInSecPerKm()->toFloat() * 1.6, $aboveUpperBoundaryGapPace->toFloat(), 0.01);
    }

    public function testFinalizeSplitGapLeavesIncompleteSplitUnchanged(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(4.0))
            ->build();

        $method = new \ReflectionMethod($this->calculateGap, 'finalizeSplitGap');
        $finalizedSplit = $method->invoke($this->calculateGap, $split, 1000.0, 999.0, 250.0, 1000.0);

        $this->assertSame($split, $finalizedSplit);
        $this->assertNull($finalizedSplit->getGapPaceInSecondsPerKm());
    }

    public function testProcessDoesNotCapSteepUphillToOldLinearBenefit(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-steep-uphill');
        $this->addActivity($activityId, SportType::RUN);

        $this->addStreams($activityId, $this->buildLinearGradeTrackPoints(0.10));
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withSplitNumber(1)
                ->withDistanceInMeter(1000.0)
                ->withAverageSpeed(MetersPerSecond::from(1000.0 / 995.0))
                ->build()
        );

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $split = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray()[0];
        $this->assertNotNull($split->getGapPaceInSecondsPerKm());
        $this->assertLessThan(
            $split->getPaceInSecPerKm()->toFloat() * 0.84,
            $split->getGapPaceInSecondsPerKm()->toFloat(),
            'Steep uphill GAP should use the segment metabolic multiplier instead of the old 16% linear cap.',
        );
    }

    public function testProcessWithUphillProducesSlowerGapThanFlat(): void
    {
        $flatActivityId = ActivityId::fromUnprefixed('run-flat');
        $uphillActivityId = ActivityId::fromUnprefixed('run-uphill');

        $this->addActivity($flatActivityId, SportType::RUN);
        $this->addActivity($uphillActivityId, SportType::RUN);

        $this->addStreams($flatActivityId, $this->buildFlatTrackPoints());
        $this->addStreams($uphillActivityId, $this->buildUphillTrackPoints());

        $this->addMetricSplits($flatActivityId, [1000.0]);
        $this->addMetricSplits($uphillActivityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $flatGap = $this->activitySplitRepository->findBy($flatActivityId, UnitSystem::METRIC)
            ->toArray()[0]->getGapPaceInSecondsPerKm();
        $uphillGap = $this->activitySplitRepository->findBy($uphillActivityId, UnitSystem::METRIC)
            ->toArray()[0]->getGapPaceInSecondsPerKm();

        $this->assertNotNull($flatGap);
        $this->assertNotNull($uphillGap);
        $this->assertLessThanOrEqual(
            $flatGap->toFloat(),
            $uphillGap->toFloat(),
            'Uphill GAP should be at least as fast as the flat equivalent at the same speed.',
        );
    }

    public function testProcessHandlesNearZeroDistanceSplit(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-tiny-split');
        $this->addActivity($activityId, SportType::RUN);
        $this->addStreams($activityId, $this->buildHillyTrackPoints());

        // A normal split followed by a near-zero distance split.
        // After segments fill the first split, the while loop advances to the
        // second split where $remainingInSplit = 0.000001 <= 0.00001,
        // triggering the early-finalize guard.
        $this->addMetricSplits($activityId, [1000.0, 0.000001]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $splits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC);
        $this->assertNotNull($splits->toArray()[0]->getGapPaceInSecondsPerKm());
        $this->assertNull($splits->toArray()[1]->getGapPaceInSecondsPerKm());
    }

    public function testProcessScalesGpsDistanceToFillAllSplits(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-scaled-distance-fills-splits');
        $this->addActivity($activityId, SportType::RUN);
        $this->addStreams($activityId, $this->buildHillyTrackPoints());

        // Track points produce less GPS distance than the three split distances.
        // The mapper scales GPS distance to split distance, so all valid splits
        // are completed through finalizeSplitGap().
        $this->addMetricSplits($activityId, [1000.0, 1000.0, 1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $splits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC);
        $this->assertNotNull($splits->toArray()[0]->getGapPaceInSecondsPerKm());
        $this->assertNotNull($splits->toArray()[1]->getGapPaceInSecondsPerKm());
        $this->assertNotNull($splits->toArray()[2]->getGapPaceInSecondsPerKm());
    }

    public function testProcessMapsOneLongSegmentAcrossMultipleSplits(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-long-segment-multiple-splits');
        $this->addActivity($activityId, SportType::RUN);
        $this->addRawStreams(
            activityId: $activityId,
            latLng: [[50.0, 4.0], [50.018, 4.0]],
            altitude: [100.0, 100.0],
            time: [0, 20],
        );
        $this->addMetricSplits($activityId, [500.0, 500.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $splits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray();
        $this->assertNotNull($splits[0]->getGapPaceInSecondsPerKm());
        $this->assertNotNull($splits[1]->getGapPaceInSecondsPerKm());
    }

    public function testMapSegmentsToSplitsScalesMultipleGpsSegmentsIntoSingleSplit(): void
    {
        $split = ActivitySplitBuilder::fromDefaults()
            ->withDistanceInMeter(1000.0)
            ->withAverageSpeed(MetersPerSecond::from(2.0))
            ->build();
        $segments = [
            GapSegment::create(1000.0, 250, 0.0, 1.0),
            GapSegment::create(1000.0, 250, 0.0, 1.0),
        ];

        $method = new \ReflectionMethod($this->calculateGap, 'mapSegmentsToSplits');
        $mappedSplits = $method->invoke($this->calculateGap, $segments, ActivitySplits::fromArray([$split]));

        $this->assertCount(1, $mappedSplits);
        $this->assertNotNull($mappedSplits[0]->getGapPaceInSecondsPerKm());
        $this->assertEqualsWithDelta(500.0, $mappedSplits[0]->getGapPaceInSecondsPerKm()->toFloat(), 0.01);
    }

    public function testProcessKeepsGapCloseToActualPaceOnGentleRollingTerrain(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-gentle-rolling');
        $this->addActivity($activityId, SportType::RUN);
        $this->addStreams($activityId, $this->buildGentleRollingTrackPoints());
        foreach ([1, 2] as $splitNumber) {
            $this->activitySplitRepository->add(
                ActivitySplitBuilder::fromDefaults()
                    ->withActivityId($activityId)
                    ->withUnitSystem(UnitSystem::METRIC)
                    ->withSplitNumber($splitNumber)
                    ->withDistanceInMeter(1000.0)
                    ->withAverageSpeed(MetersPerSecond::from(1000.0 / 657.0))
                    ->build()
            );
        }

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $splits = $this->activitySplitRepository->findBy($activityId, UnitSystem::METRIC)->toArray();

        foreach ($splits as $split) {
            $gapPace = $split->getGapPaceInSecondsPerKm();
            $this->assertNotNull($gapPace);
            $this->assertLessThan(
                60.0,
                abs($gapPace->toFloat() - $split->getPaceInSecPerKm()->toFloat()),
                'GAP should stay reasonably close to actual pace on gentle terrain.',
            );
        }
    }

    public function testProcessOutputsProgress(): void
    {
        $activityId = ActivityId::fromUnprefixed('run-progress');
        $this->addActivity($activityId, SportType::RUN);
        $this->addStreams($activityId, $this->buildHillyTrackPoints());
        $this->addMetricSplits($activityId, [1000.0]);

        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $this->assertStringContainsString('Calculated GAP for 1 activities', (string) $output);
    }

    public function testProcessWithNoActivitiesToProcess(): void
    {
        $output = new SpyOutput();
        $this->calculateGap->process($output);

        $this->assertStringContainsString('Calculated GAP for 0 activities', (string) $output);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateGap = $this->getContainer()->get(CalculateGap::class);
        $this->activitySplitRepository = $this->getContainer()->get(ActivitySplitRepository::class);
        $this->activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
    }

    private function addActivity(ActivityId $activityId, SportType $sportType): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withSportType($sportType)
                ->build(),
            [],
        ));
    }

    /**
     * @param list<float> $splitDistances
     */
    private function addMetricSplits(ActivityId $activityId, array $splitDistances): void
    {
        foreach ($splitDistances as $index => $distance) {
            $this->activitySplitRepository->add(
                ActivitySplitBuilder::fromDefaults()
                    ->withActivityId($activityId)
                    ->withUnitSystem(UnitSystem::METRIC)
                    ->withSplitNumber($index + 1)
                    ->withDistanceInMeter($distance)
                    ->build()
            );
        }
    }

    private function addMetricSplitWithSpeed(ActivityId $activityId, float $distanceInMeters, float $metersPerSecond, int $splitNumber = 1): void
    {
        $this->activitySplitRepository->add(
            ActivitySplitBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withUnitSystem(UnitSystem::METRIC)
                ->withSplitNumber($splitNumber)
                ->withDistanceInMeter($distanceInMeters)
                ->withAverageSpeed(MetersPerSecond::from($metersPerSecond))
                ->build()
        );
    }

    /**
     * @param list<mixed> $latLng
     * @param list<float> $altitude
     * @param list<int>   $time
     * @param list<bool>  $moving
     */
    private function addRawStreams(ActivityId $activityId, array $latLng, array $altitude, array $time, array $moving = []): void
    {
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::LAT_LNG)
                ->withData($latLng)
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::ALTITUDE)
                ->withData($altitude)
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::TIME)
                ->withData($time)
                ->build()
        );
        if ([] === $moving) {
            return;
        }

        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::MOVING)
                ->withData($moving)
                ->build()
        );
    }

    private function flatGapSegment(float $distanceInMeters, int $durationInSeconds, float $multiplier = 1.0): GapSegment
    {
        return GapSegment::create(
            distanceInMeters: $distanceInMeters,
            durationInSeconds: $durationInSeconds,
            grade: 0.0,
            gapMultiplier: $multiplier,
        );
    }

    /**
     * @param list<array{latLng: array<int, array{float, float}>, altitude: list<float>, time: list<int>}> $trackPoints
     */
    private function addStreams(ActivityId $activityId, array $trackPoints): void
    {
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::LAT_LNG)
                ->withData($trackPoints['latLng'])
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::ALTITUDE)
                ->withData($trackPoints['altitude'])
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withStreamType(StreamType::TIME)
                ->withData($trackPoints['time'])
                ->build()
        );
    }

    /**
     * @return array{latLng: list<array{float, float}>, altitude: list<float>, time: list<int>}
     */
    private function buildHillyTrackPoints(): array
    {
        $latLng = [];
        $altitude = [];
        $time = [];

        for ($i = 0; $i < 200; ++$i) {
            $latLng[] = [50.0 + $i * 0.00009, 4.0 + $i * 0.00009];
            $altitude[] = 100.0 + 30.0 * sin($i * 0.05);
            $time[] = $i * 5;
        }

        return ['latLng' => $latLng, 'altitude' => $altitude, 'time' => $time];
    }

    /**
     * @return array{latLng: list<array{float, float}>, altitude: list<float>, time: list<int>}
     */
    private function buildFlatTrackPoints(): array
    {
        $latLng = [];
        $altitude = [];
        $time = [];

        for ($i = 0; $i < 200; ++$i) {
            $latLng[] = [50.0 + $i * 0.00009, 4.0];
            $altitude[] = 100.0;
            $time[] = $i * 5;
        }

        return ['latLng' => $latLng, 'altitude' => $altitude, 'time' => $time];
    }

    /**
     * @return array{latLng: list<array{float, float}>, altitude: list<float>, time: list<int>}
     */
    private function buildUphillTrackPoints(): array
    {
        $latLng = [];
        $altitude = [];
        $time = [];

        for ($i = 0; $i < 200; ++$i) {
            $latLng[] = [50.0 + $i * 0.00009, 4.0];
            $altitude[] = 100.0 + $i * 1.5;
            $time[] = $i * 5;
        }

        return ['latLng' => $latLng, 'altitude' => $altitude, 'time' => $time];
    }

    /**
     * @return array{latLng: list<array{float, float}>, altitude: list<float>, time: list<int>}
     */
    private function buildLinearGradeTrackPoints(float $grade, int $timeStepInSeconds = 5): array
    {
        $latLng = [];
        $altitude = [];
        $time = [];

        for ($i = 0; $i < 200; ++$i) {
            $latLng[] = [50.0 + $i * 0.00009, 4.0];
            $altitude[] = 100.0 + ($i * 10.0 * $grade);
            $time[] = $i * $timeStepInSeconds;
        }

        return ['latLng' => $latLng, 'altitude' => $altitude, 'time' => $time];
    }

    /**
     * @return array{latLng: list<array{float, float}>, altitude: list<float>, time: list<int>}
     */
    private function buildRollingNetFlatTrackPoints(): array
    {
        $latLng = [];
        $altitude = [];
        $time = [];

        for ($i = 0; $i < 200; ++$i) {
            $latLng[] = [50.0 + $i * 0.00009, 4.0];
            $altitude[] = 100.0 + 25.0 * sin((2.0 * M_PI * $i) / 199.0);
            $time[] = $i * 5;
        }

        return ['latLng' => $latLng, 'altitude' => $altitude, 'time' => $time];
    }

    /**
     * @return array{latLng: list<array{float, float}>, altitude: list<float>, time: list<int>}
     */
    private function buildGentleRollingTrackPoints(): array
    {
        $latLng = [];
        $altitude = [];
        $time = [];

        for ($i = 0; $i < 220; ++$i) {
            $latLng[] = [50.0 + $i * 0.00009, 4.0];
            $altitude[] = 100.0 + 0.5 * sin($i * 0.08);
            $time[] = $i * 6;
        }

        return ['latLng' => $latLng, 'altitude' => $altitude, 'time' => $time];
    }
}
