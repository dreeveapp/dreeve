<?php

namespace App\Application\Import\StravaImport\ProcessRawActivityData\Pipeline;

use App\Application\Countries;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentEffort\SegmentEffort;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ProcessActivitySegmentEfforts implements ProcessRawDataStep
{
    public function __construct(
        private ActivityIdRepository $activityIdRepository,
        private ActivityRepository $activityRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private SegmentRepository $segmentRepository,
        private Countries $countries,
    ) {
    }

    public function process(OutputInterface $output): void
    {
        $segmentsProcessedInCurrentRun = [];

        $countSegmentsAdded = 0;
        $countSegmentEffortsAdded = 0;

        foreach ($this->activityIdRepository->findAll() as $activityId) {
            $activityWithRawData = $this->activityRepository->findWithRawData($activityId);
            if (!$segmentEfforts = $activityWithRawData->getSegmentEfforts()) {
                continue;
            }

            $activity = $activityWithRawData->getActivity();
            foreach ($segmentEfforts as $activitySegmentEffort) {
                $activitySegment = $activitySegmentEffort['segment'];
                $segmentId = SegmentId::fromUnprefixed((string) $activitySegment['id']);

                $countryCode = null;
                if ($activity->getSportType()->supportsReverseGeocoding() && !empty($activitySegment['country'])) {
                    $countryCode = $this->countries->findCountryCodeByCountryName($activitySegment['country']);
                }

                $isFavourite = isset($activitySegment['starred']) && $activitySegment['starred'];

                // Do not process segments that have been imported in the current run.
                if (!isset($segmentsProcessedInCurrentRun[(string) $segmentId])) {
                    try {
                        $segment = $this->segmentRepository->find($segmentId);
                        if ($isFavourite !== $segment->isFavourite()) {
                            $segment->updateIsFavourite($isFavourite);
                            $this->segmentRepository->update($segment);
                        }
                    } catch (EntityNotFound) {
                        $segment = Segment::create(
                            segmentId: $segmentId,
                            name: Name::fromString($activitySegment['name']),
                            sportType: $activity->getSportType(),
                            distance: Meter::from($activitySegment['distance'])->toKilometer(),
                            maxGradient: $activitySegment['maximum_grade'],
                            isFavourite: $isFavourite,
                            climbCategory: $activitySegment['climb_category'] ?? null,
                            deviceName: $activity->getDeviceName(),
                            countryCode: $countryCode,
                            averageGradient: $activitySegment['average_grade'] ?? null,
                        );
                        $this->segmentRepository->add($segment);
                        ++$countSegmentsAdded;
                    }
                    $segmentsProcessedInCurrentRun[(string) $segmentId] = $segmentId;
                }

                $segmentEffortId = SegmentEffortId::fromUnprefixed((string) $activitySegmentEffort['id']);
                try {
                    $this->segmentEffortRepository->find($segmentEffortId);
                } catch (EntityNotFound) {
                    $this->segmentEffortRepository->add(SegmentEffort::create(
                        segmentEffortId: $segmentEffortId,
                        segmentId: $segmentId,
                        activityId: $activity->getId(),
                        startDateTime: SerializableDateTime::createFromFormat(
                            Activity::DATE_TIME_FORMAT,
                            $activitySegmentEffort['start_date_local']
                        ),
                        name: $activitySegmentEffort['name'],
                        elapsedTimeInSeconds: (float) $activitySegmentEffort['elapsed_time'],
                        distance: Meter::from($activitySegment['distance'])->toKilometer(),
                        averageWatts: isset($activitySegmentEffort['average_watts']) ? (float) $activitySegmentEffort['average_watts'] : null,
                        averageHeartRate: isset($activitySegmentEffort['average_heartrate']) ? (int) $activitySegmentEffort['average_heartrate'] : null,
                        maxHeartRate: isset($activitySegmentEffort['max_heartrate']) ? (int) $activitySegmentEffort['max_heartrate'] : null,
                    ));
                    ++$countSegmentEffortsAdded;
                }
            }
        }
        $output->writeln(sprintf('  => Added %d new segments and %d new segment efforts', $countSegmentsAdded, $countSegmentEffortsAdded));
    }
}
