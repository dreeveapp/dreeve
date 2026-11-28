<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\MediaLocation;

use App\Domain\Activity\Activity;
use App\Domain\Activity\Route\ActivityRouteLocator;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Integration\AI\AIPlatformFactory;
use App\Domain\Media\MediaItem;
use App\Domain\Media\Metadata\MediaItemMetadataReader;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use League\Flysystem\FilesystemOperator;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class MediaItemLocationSuggester
{
    private const int MAX_IMAGE_EDGE_IN_PIXELS = 1568;
    private const int ROUTE_PROFILE_SAMPLES = 200;
    private const string INSTRUCTIONS = <<<'PROMPT'
        # IDENTITY AND PURPOSE
        You determine where along a recorded activity route a photo was taken.

        # INPUT
        - The photo.
        - Details about the activity: name, description, sport type and start time.
        - The time the photo was taken on, if known. Treat it as a hint only: the camera clock can be wrong or set to another time zone.
        - A route profile: samples along the route with the distance from the start in km, the elapsed time, the clock time, the altitude in metres and the coordinate.

        # INTERNAL ASSISTANT STEPS
        - Look at everything the photo shows: landscape, terrain, slope, buildings, landmarks, road surface, signs, water, vegetation, light, shadows and weather.
        - Match it against the route profile, the altitude and your knowledge of the area around the coordinates.
        - Pick the single most likely distance along the route.

        # OUTPUT INSTRUCTIONS
        - Answer with a single JSON object and nothing else, no markdown:
          {"distanceInKm": float|null, "confidence": "low|medium|high", "reasoning": "<one or two sentences>"}
        - distanceInKm lies between the first and the last km in the route profile.
        - Use null for distanceInKm when the photo gives no clue about where on this route it was taken.
        - Keep the reasoning to one or two sentences that explain which clues you used.
        PROMPT;

    public function __construct(
        private AIPlatformFactory $platformFactory,
        private ActivityStreamRepository $activityStreamRepository,
        private ActivityRouteLocator $activityRouteLocator,
        private MediaItemMetadataReader $mediaItemMetadataReader,
        #[Target('file.storage')]
        private FilesystemOperator $fileStorage,
    ) {
    }

    public function suggest(Activity $activity, MediaItem $mediaItem, ?SerializableDateTime $takenOn): ?LocationSuggestion
    {
        $streams = $this->activityStreamRepository->findByActivityId($activity->getId());
        $distances = array_values($streams->filterOnType(StreamType::DISTANCE)?->getData() ?? []);
        if ([] === $distances) {
            return null;
        }
        $times = array_values($streams->filterOnType(StreamType::TIME)?->getData() ?? []);
        $altitudes = array_values($streams->filterOnType(StreamType::ALTITUDE)?->getData() ?? []);
        $latLngs = array_values($streams->filterOnType(StreamType::LAT_LNG)?->getData() ?? []);

        $indexes = range(0, count($distances) - 1, max(1, (int) ceil(count($distances) / self::ROUTE_PROFILE_SAMPLES)));
        if (array_last($indexes) !== count($distances) - 1) {
            $indexes[] = count($distances) - 1;
        }

        $routeProfile = ['km | elapsed time | clock time | altitude (m) | lat,lng'];
        foreach ($indexes as $index) {
            $routeProfile[] = implode(' | ', [
                sprintf('%.2f', $distances[$index] / 1000),
                isset($times[$index]) ? gmdate('H:i:s', (int) $times[$index]) : '-',
                isset($times[$index]) ? $activity->getStartDate()->modify(sprintf('+%d seconds', $times[$index]))->format('H:i:s') : '-',
                isset($altitudes[$index]) ? (string) round($altitudes[$index]) : '-',
                isset($latLngs[$index][0], $latLngs[$index][1]) ? sprintf('%.5f,%.5f', $latLngs[$index][0], $latLngs[$index][1]) : '-',
            ]);
        }

        $content = $this->fileStorage->read($mediaItem->getPath()->toString());
        $source = imagecreatefromstring($content) ?: throw new \RuntimeException(sprintf('Could not read media item "%s" as an image', $mediaItem->getId()));
        $exifOrientation = $this->mediaItemMetadataReader->read($content)->getExifOrientation();
        if (0 !== ($degrees = $exifOrientation->clockwiseRotationInDegrees())) {
            $source = imagerotate($source, -$degrees, 0) ?: throw new \RuntimeException(sprintf('Could not rotate media item "%s"', $mediaItem->getId()));
        }
        if ($exifOrientation->mirrorsHorizontally()) {
            imageflip($source, IMG_FLIP_HORIZONTAL);
        }
        if ($exifOrientation->mirrorsVertically()) {
            imageflip($source, IMG_FLIP_VERTICAL);
        }
        $scale = min(1, self::MAX_IMAGE_EDGE_IN_PIXELS / max(imagesx($source), imagesy($source)));
        $image = imagescale($source, max(1, (int) round(imagesx($source) * $scale)), max(1, (int) round(imagesy($source) * $scale))) ?: throw new \RuntimeException(sprintf('Could not downscale media item "%s"', $mediaItem->getId()));
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $prompt = implode("\n", [
            '# ACTIVITY',
            'Name: '.$activity->getName(),
            'Description: '.($activity->getDescription() ?: '-'),
            'Sport type: '.$activity->getSportType()->value,
            'Start time: '.$activity->getStartDate()->format('Y-m-d H:i:s'),
            '',
            '# PHOTO',
            'Taken on: '.($takenOn?->format('Y-m-d H:i:s') ?? 'unknown'),
            '',
            '# ROUTE PROFILE',
            ...$routeProfile,
        ]);

        $answer = $this->platformFactory->createPlatform([
            Capability::INPUT_MESSAGES,
            Capability::INPUT_IMAGE,
            Capability::OUTPUT_TEXT,
        ])->invoke(
            model: $this->platformFactory->getModel(),
            input: new MessageBag(
                Message::forSystem(self::INSTRUCTIONS),
                Message::ofUser($prompt, new Image($jpeg, 'image/jpeg')),
            ),
        )->asText();

        if (!preg_match('/\{.*\}/s', $answer, $matches)) {
            return null;
        }

        try {
            $suggestion = Json::decode($matches[0]);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($suggestion) || !is_numeric($suggestion['distanceInKm'] ?? null)) {
            return null;
        }

        $coordinate = $this->activityRouteLocator->locateAtDistance(
            activity: $activity,
            distance: Meter::from((float) $suggestion['distanceInKm'] * 1000),
        );
        if (!$coordinate instanceof Coordinate) {
            return null;
        }

        return LocationSuggestion::create(
            coordinate: $coordinate,
            confidence: LocationSuggestionConfidence::tryFrom(is_string($suggestion['confidence'] ?? null) ? $suggestion['confidence'] : '') ?? LocationSuggestionConfidence::LOW,
            reasoning: is_string($suggestion['reasoning'] ?? null) ? trim($suggestion['reasoning']) : '',
        );
    }
}
