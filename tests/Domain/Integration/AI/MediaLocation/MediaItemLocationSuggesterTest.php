<?php

declare(strict_types=1);

namespace App\Tests\Domain\Integration\AI\MediaLocation;

use App\Domain\Activity\Activity;
use App\Domain\Activity\Route\ActivityRouteLocator;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Integration\AI\AIPlatformFactory;
use App\Domain\Integration\AI\MediaLocation\LocationSuggestion;
use App\Domain\Integration\AI\MediaLocation\LocationSuggestionConfidence;
use App\Domain\Integration\AI\MediaLocation\MediaItemLocationSuggester;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\Metadata\MediaItemMetadataReader;
use App\Domain\Settings\AISettings;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class MediaItemLocationSuggesterTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ?string $originalAIApiKey = null;

    public function testItSuggestsALocationOnTheRoute(): void
    {
        $requestBodies = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestBodies): MockResponse {
            $requestBodies[] = Json::decode($options['body']);

            return new MockResponse((string) file_get_contents(__DIR__.'/fixtures/anthropic-response-with-suggestion.json'));
        });
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $activity = ActivityBuilder::fromDefaults()
            ->withName('Summit ride')
            ->withDescription('Up and down the hill')
            ->withSportType(SportType::GRAVEL_RIDE)
            ->withStartDateTime(SerializableDateTime::fromString('2026-06-01 10:00:00'))
            ->build();
        $this->givenRouteStreams(
            activityStreamRepository: $activityStreamRepository,
            activity: $activity,
        );

        $fileStorage = $this->getContainer()->get('file.storage');
        $fileStorage->write('activities/photo.jpg', (string) file_get_contents(__DIR__.'/fixtures/photo-with-orientation-6.jpg'));

        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository->method('ai')->willReturn(AISettings::fromArray(['ai' => [
            'enabled' => true,
            'provider' => 'anthropic',
            'configuration' => ['model' => 'model'],
        ]]));

        $suggestion = new MediaItemLocationSuggester(
            platformFactory: new AIPlatformFactory(
                settingsRepository: $settingsRepository,
                httpClient: $httpClient,
            ),
            activityStreamRepository: $activityStreamRepository,
            activityRouteLocator: new ActivityRouteLocator($activityStreamRepository),
            mediaItemMetadataReader: new MediaItemMetadataReader(),
            fileStorage: $fileStorage,
        )->suggest(
            activity: $activity,
            mediaItem: MediaItemBuilder::fromDefaults()
                ->withPath(MediaItemPath::fromString('activities/photo.jpg'))
                ->build(),
            takenOn: SerializableDateTime::fromString('2026-06-01 10:25:00'),
        );

        $this->assertEquals(
            LocationSuggestion::create(
                coordinate: Coordinate::createFromLatAndLng(Latitude::fromString('51.03'), Longitude::fromString('4.03')),
                confidence: LocationSuggestionConfidence::HIGH,
                reasoning: 'The photo shows a summit cairn, which matches the highest point of the route around km 6.',
            ),
            $suggestion,
        );

        $this->assertCount(1, $requestBodies);
        [$text, $image] = $requestBodies[0]['messages'][0]['content'];
        $this->assertMatchesTextSnapshot($text['text']);
        $photo = imagecreatefromstring(base64_decode((string) $image['source']['data'], true) ?: '') ?: throw new \RuntimeException('The sent photo is not an image');
        $this->assertSame([784, 1568], [imagesx($photo), imagesy($photo)]);
        $this->assertGreaterThan(200, imagecolorat($photo, 770, 10) >> 16 & 0xFF);
        $this->assertLessThan(50, imagecolorat($photo, 10, 10) >> 16 & 0xFF);
    }

    public function testItReturnsNullWhenThereIsNoSuggestion(): void
    {
        $httpClient = new MockHttpClient(new MockResponse((string) file_get_contents(__DIR__.'/fixtures/anthropic-response-without-suggestion.json')));
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->givenRouteStreams(
            activityStreamRepository: $activityStreamRepository,
            activity: $activity,
        );

        $fileStorage = $this->getContainer()->get('file.storage');
        $fileStorage->write('activities/photo.jpg', (string) file_get_contents(__DIR__.'/fixtures/photo-with-orientation-6.jpg'));

        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository->method('ai')->willReturn(AISettings::fromArray(['ai' => [
            'enabled' => true,
            'provider' => 'anthropic',
            'configuration' => ['model' => 'model'],
        ]]));

        $this->assertNull(new MediaItemLocationSuggester(
            platformFactory: new AIPlatformFactory(
                settingsRepository: $settingsRepository,
                httpClient: $httpClient,
            ),
            activityStreamRepository: $activityStreamRepository,
            activityRouteLocator: new ActivityRouteLocator($activityStreamRepository),
            mediaItemMetadataReader: new MediaItemMetadataReader(),
            fileStorage: $fileStorage,
        )->suggest(
            activity: $activity,
            mediaItem: MediaItemBuilder::fromDefaults()
                ->withPath(MediaItemPath::fromString('activities/photo.jpg'))
                ->build(),
            takenOn: null,
        ));
    }

    private function givenRouteStreams(ActivityStreamRepository $activityStreamRepository, Activity $activity): void
    {
        $indexes = range(0, 600);
        foreach ([
            StreamType::TIME->value => array_map(static fn (int $i): int => $i * 5, $indexes),
            StreamType::DISTANCE->value => array_map(static fn (int $i): int => $i * 20, $indexes),
            StreamType::ALTITUDE->value => array_map(static fn (int $i): float => 400.0 - abs($i - 300), $indexes),
            StreamType::LAT_LNG->value => array_map(static fn (int $i): array => [51 + $i * 0.0001, 4 + $i * 0.0001], $indexes),
        ] as $streamType => $data) {
            $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId($activity->getId())
                ->withStreamType(StreamType::from($streamType))
                ->withData($data)
                ->build());
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalAIApiKey = $_SERVER['AI_API_KEY'];
        $_SERVER['AI_API_KEY'] = 'sk-key';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $_SERVER['AI_API_KEY'] = $this->originalAIApiKey;
    }
}
