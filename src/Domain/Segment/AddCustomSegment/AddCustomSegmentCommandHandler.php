<?php

declare(strict_types=1);

namespace App\Domain\Segment\AddCustomSegment;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Route\RouteGeographyAnalyzer;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;

final readonly class AddCustomSegmentCommandHandler implements CommandHandler
{
    private const int MIN_DISTANCE_IN_METERS = 100;
    private const int MAX_GRADIENT_WINDOW_IN_METERS = 50;

    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private SegmentRepository $segmentRepository,
        private RouteGeographyAnalyzer $routeGeographyAnalyzer,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof AddCustomSegment);

        if (!$this->activityRepository->exists($command->getActivityId())) {
            throw CouldNotProcessCommand::withReason('The selected activity does not exist.');
        }

        $activity = $this->activityRepository->find($command->getActivityId());
        $streams = $this->activityStreamRepository->findByActivityId($command->getActivityId());
        $latLng = array_values($streams->filterOnType(StreamType::LAT_LNG)?->getData() ?? []);
        $distance = array_values($streams->filterOnType(StreamType::DISTANCE)?->getData() ?? []);
        $altitude = array_values($streams->filterOnType(StreamType::ALTITUDE)?->getData() ?? []);

        if ([] === $latLng || [] === $distance) {
            throw CouldNotProcessCommand::withReason('The selected activity has no GPS data.');
        }

        $startIndex = $command->getStartIndex();
        $endIndex = $command->getEndIndex();
        if ($endIndex >= min(count($latLng), count($distance))) {
            throw CouldNotProcessCommand::withReason('The selected start and end are not part of the activity route.');
        }

        $segmentDistance = $distance[$endIndex] - $distance[$startIndex];
        if ($segmentDistance < self::MIN_DISTANCE_IN_METERS) {
            throw CouldNotProcessCommand::withReason(sprintf('A segment needs to be at least %d meters long.', self::MIN_DISTANCE_IN_METERS));
        }

        $averageGradient = null;
        $maxGradient = 0.0;
        if (count($altitude) > $endIndex) {
            $averageGradient = round(($altitude[$endIndex] - $altitude[$startIndex]) / $segmentDistance * 100, 1);
            $maxGradient = $averageGradient;
            $windowEndIndex = $startIndex;
            for ($index = $startIndex; $index <= $endIndex; ++$index) {
                while ($windowEndIndex <= $endIndex && $distance[$windowEndIndex] - $distance[$index] < self::MAX_GRADIENT_WINDOW_IN_METERS) {
                    ++$windowEndIndex;
                }
                if ($windowEndIndex > $endIndex) {
                    break;
                }
                $maxGradient = max(
                    $maxGradient,
                    round(($altitude[$windowEndIndex] - $altitude[$index]) / ($distance[$windowEndIndex] - $distance[$index]) * 100, 1),
                );
            }
        }

        $polyline = EncodedPolyline::fromCoordinates(array_slice($latLng, $startIndex, $endIndex - $startIndex + 1));
        $countryCode = null;
        if ($activity->getSportType()->supportsReverseGeocoding() && ($countryCodes = $this->routeGeographyAnalyzer->analyzeForPolyline($polyline))) {
            $countryCode = strtolower($countryCodes[0]);
        }

        $this->segmentRepository->add(Segment::createCustom(
            segmentId: SegmentId::random(),
            name: $command->getName(),
            sportType: $activity->getSportType(),
            distance: Meter::from($segmentDistance)->toKilometer(),
            maxGradient: $maxGradient,
            averageGradient: $averageGradient,
            isFavourite: $command->isFavourite(),
            countryCode: $countryCode,
            polyline: $polyline,
        ));
    }
}
